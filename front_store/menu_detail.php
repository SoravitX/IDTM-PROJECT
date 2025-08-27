<?php
// SelectRole/menu_detail.php — หน้าเลือกตัวเลือก + ส่งกลับแบบแยกรายการตามรายละเอียด (แก้โหมดแก้ไขไม่พก note เดิม)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$menu_id = (int)($_GET['id'] ?? 0);
if ($menu_id <= 0) { header("Location: front_store.php"); exit; }

$stmt = $conn->prepare("SELECT m.menu_id, m.name, m.price, m.image, c.category_name
                        FROM menu m LEFT JOIN categories c ON m.category_id=c.category_id
                        WHERE m.menu_id=?");
$stmt->bind_param("i", $menu_id);
$stmt->execute();
$menu = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$menu) { header("Location: front_store.php"); exit; }

// ไฟล์ภาพ
$img = trim((string)$menu['image']);
$imgPathFs = __DIR__ . "/../admin/images/" . ($img !== '' ? $img : "default.png");
$imgSrc    = "../admin/images/" . ($img !== '' ? $img : "default.png");
if (!file_exists($imgPathFs)) { $imgSrc = "https://via.placeholder.com/800x600?text=No+Image"; }

function money_fmt($n){ return number_format((float)$n, 2); }

/* โหมดแก้ไข:
 * รับ key เดิม (ของตะกร้า) มาเพื่อโหลดค่าเริ่มต้น และเวลาส่งกลับให้ลบ/ย้ายไปแถวใหม่หากรายละเอียดเปลี่ยน
 */
$editMode = (int)($_GET['edit'] ?? 0) === 1;
$old_key  = isset($_GET['key']) ? (string)$_GET['key'] : '';

$currentQty   = 1;
$currentNote  = '';

if ($editMode && $old_key !== '' && isset($_SESSION['cart'][$old_key])) {
  $currentQty  = max(1, (int)($_SESSION['cart'][$old_key]['qty'] ?? 1));
  $currentNote = (string)($_SESSION['cart'][$old_key]['note'] ?? '');
}

/* ✅ แตก note เดิมออกเป็นส่วนๆ เพื่อ “ตั้งค่าปุ่ม” ให้ตรงกับของเดิม
   ฟอร์แมตที่เราสร้างไว้ตอน submit:
   "ขนาด: ธรรมดา | หวาน: ปกติ | น้ำแข็ง: ปกติ | ท็อปปิง: ไข่มุก, เจลลี่ | หมายเหตุ: งดหลอด"
*/
$selSize    = 'ธรรมดา';
$selSweet   = 'ปกติ';
$selIce     = 'ปกติ';
$selToppings= [];
$selFree    = '';

if ($editMode && $currentNote !== '') {
  $parts = explode(' | ', $currentNote);
  foreach ($parts as $p) {
    $p = trim($p);
    if (stripos($p, 'ขนาด:') === 0) {
      $selSize = trim(mb_substr($p, mb_strlen('ขนาด:')));
    } elseif (stripos($p, 'หวาน:') === 0) {
      $selSweet = trim(mb_substr($p, mb_strlen('หวาน:')));
    } elseif (stripos($p, 'น้ำแข็ง:') === 0) {
      $selIce = trim(mb_substr($p, mb_strlen('น้ำแข็ง:')));
    } elseif (stripos($p, 'ท็อปปิง:') === 0) {
      $tpStr = trim(mb_substr($p, mb_strlen('ท็อปปิง:')));
      if ($tpStr !== '') {
        $selToppings = array_map('trim', explode(',', $tpStr));
      }
    } elseif (stripos($p, 'หมายเหตุ:') === 0) {
      $selFree = trim(mb_substr($p, mb_strlen('หมายเหตุ:')));
    }
  }
}

// รายการท็อปปิง (ตัวอย่างคงที่ — สามารถดึงจาก DB จริงได้)
$toppings = ['ไข่มุก','เจลลี่','พุดดิ้ง','วิปครีม'];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เลือกตัวเลือก • <?= htmlspecialchars($menu['name'],ENT_QUOTES,'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root {
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman-blue:#0094B3;
  --psu-sky-blue:#29ABE2; --psu-river-blue:#4EC5E0; --psu-sritrang:#BBB4D8;
}
body{ background: linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue)); color:#fff; font-family:"Segoe UI", Tahoma, sans-serif; }
.wrap{ max-width:980px; margin:24px auto; padding:12px; }
.cardx{ background:rgba(255,255,255,.08); border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:0 10px 28px rgba(0,0,0,.25); overflow:hidden; }
.thumb{ width:100%; height:260px; object-fit:cover; background:#2b6aa3; }
.hd{ padding:12px 16px; }
.hd h4{ margin:0; font-weight:800; }
.badge-cat{ display:inline-block; background:#e9f4ff; color:#084a87; border:1px solid #bcd6f3; border-radius:999px; padding:2px 10px; font-weight:700; font-size:.85rem; }
.form-area{ padding:16px; }
.sec-title{ font-weight:800; margin-top:8px; color:#fff; }
label{ color:#fff; font-weight:600; }
.note-hint{ font-size:.85rem; color:#e8f2ff; opacity:.9; }
.btn-back{ color:#fff; }
</style>
</head>
<body>
<div class="wrap">
  <div class="mb-3">
    <a href="front_store.php" class="btn btn-back">&larr; กลับหน้าขาย</a>
  </div>

  <div class="cardx">
    <img src="<?= htmlspecialchars($imgSrc,ENT_QUOTES,'UTF-8') ?>" class="thumb" alt="">
    <div class="hd d-flex justify-content-between align-items-center">
      <div>
        <h4><?= htmlspecialchars($menu['name'],ENT_QUOTES,'UTF-8') ?></h4>
        <div class="badge-cat mt-1"><?= htmlspecialchars($menu['category_name'] ?? 'เมนู',ENT_QUOTES,'UTF-8') ?></div>
      </div>
      <div class="h5 m-0 font-weight-bold"><?= money_fmt($menu['price']) ?> ฿</div>
    </div>

    <div class="form-area">
      <form method="post" action="front_store.php" id="menuForm">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="menu_id" value="<?= (int)$menu['menu_id'] ?>">
        <?php if ($editMode): ?>
          <input type="hidden" name="edit" value="1">
          <input type="hidden" name="old_key" value="<?= htmlspecialchars($old_key,ENT_QUOTES,'UTF-8') ?>">
        <?php endif; ?>

        <div class="row">
          <div class="col-md-6">
            <div class="sec-title">ระดับความหวาน</div>
            <?php
              $sweetOpts = ['หวานน้อย','ปกติ','หวานมาก'];
              foreach($sweetOpts as $i=>$sv):
                $id = 'sweet'.($i+1);
                $checked = ($selSweet === $sv) ? 'checked' : '';
            ?>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" name="sweet" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="custom-control-label" for="<?= $id ?>"><?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
            </div>
            <?php endforeach; ?>

            <div class="sec-title mt-3">น้ำแข็ง</div>
            <?php
              $iceOpts = ['ไม่ใส่น้ำแข็ง','ปกติ','เยอะ'];
              foreach($iceOpts as $i=>$iv):
                $id = 'ice'.($i+1);
                $checked = ($selIce === $iv) ? 'checked' : '';
            ?>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" name="ice" id="<?= $id ?>" value="<?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="custom-control-label" for="<?= $id ?>"><?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?></label>
            </div>
            <?php endforeach; ?>

            <div class="sec-title mt-3">ขนาด</div>
            <?php
              $sizeOpts = ['เล็ก','ธรรมดา','ใหญ่'];
              foreach($sizeOpts as $i=>$sv):
                $id = 'size'.($i+1);
                $checked = ($selSize === $sv) ? 'checked' : '';
            ?>
            <div class="custom-control custom-radio">
              <input class="custom-control-input" type="radio" name="size" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="custom-control-label" for="<?= $id ?>"><?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="col-md-6">
            <div class="sec-title">ท็อปปิง (เลือกได้หลายอย่าง)</div>
            <?php foreach($toppings as $i=>$tp):
              $id = 'tp'.($i+1);
              $checked = in_array($tp, $selToppings, true) ? 'checked' : '';
            ?>
            <div class="custom-control custom-checkbox">
              <input class="custom-control-input" type="checkbox" name="toppings[]" id="<?= $id ?>" value="<?= htmlspecialchars($tp,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="custom-control-label" for="<?= $id ?>"><?= htmlspecialchars($tp,ENT_QUOTES,'UTF-8') ?></label>
            </div>
            <?php endforeach; ?>

            <div class="sec-title mt-3">หมายเหตุเพิ่มเติม</div>
            <textarea class="form-control" name="note_free" rows="3" placeholder="เช่น ไม่ใส่ฝา งดหลอด ฯลฯ"><?= htmlspecialchars($selFree,ENT_QUOTES,'UTF-8') ?></textarea>

            <div class="sec-title mt-3">จำนวน</div>
            <input type="number" class="form-control" name="qty" value="<?= (int)$currentQty ?>" min="1" style="max-width:160px">
          </div>
        </div>

        <input type="hidden" name="note" id="note">

        <div class="mt-4 d-flex justify-content-between">
          <a href="front_store.php" class="btn btn-secondary">ยกเลิก</a>
          <button type="submit" class="btn btn-success"><?= $editMode ? 'บันทึกการแก้ไข' : 'เพิ่มในตะกร้า' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// รวมตัวเลือกเป็น note เดียวก่อนส่ง (เป็น normalized text)
const form = document.getElementById('menuForm');
form.addEventListener('submit', function(){
  const get = sel => {
    const el = document.querySelector(sel+':checked');
    return el ? el.value : '';
  };
  const sweet = get('input[name="sweet"]');
  const ice   = get('input[name="ice"]');
  const size  = get('input[name="size"]');

  const tops = Array.from(document.querySelectorAll('input[name="toppings[]"]:checked')).map(x=>x.value);
  const free = (document.querySelector('textarea[name="note_free"]')?.value || '').trim();

  const parts = [];
  if (size)  parts.push('ขนาด: '+size);
  if (sweet) parts.push('หวาน: '+sweet);
  if (ice)   parts.push('น้ำแข็ง: '+ice);
  if (tops.length) parts.push('ท็อปปิง: '+tops.join(', '));
  if (free) parts.push('หมายเหตุ: '+free);

  document.getElementById('note').value = parts.join(' | ');
});
</script>
</body>
</html>

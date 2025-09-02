<?php
// SelectRole/menu_detail.php — Standalone + Popup fragment (PSU tone)
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

$img = trim((string)$menu['image']);
$imgPathFs = __DIR__ . "/../admin/images/" . ($img !== '' ? $img : "default.png");
$imgSrc    = "../admin/images/" . ($img !== '' ? $img : "default.png");
if (!file_exists($imgPathFs)) { $imgSrc = "https://via.placeholder.com/800x600?text=No+Image"; }

function money_fmt($n){ return number_format((float)$n, 2); }

/* ----- โหมดแก้ไข (เติมค่าเดิม) ----- */
$editMode   = (int)($_GET['edit'] ?? 0) === 1;
$old_key    = isset($_GET['key']) ? (string)$_GET['key'] : '';
$currentQty = 1; $currentNote = '';

if ($editMode && $old_key !== '' && isset($_SESSION['cart'][$old_key])) {
  $currentQty  = max(1, (int)($_SESSION['cart'][$old_key]['qty'] ?? 1));
  $currentNote = (string)($_SESSION['cart'][$old_key]['note'] ?? '');
}

$selSize='ธรรมดา'; $selSweet='ปกติ'; $selIce='ปกติ'; $selToppings=[]; $selFree='';
if ($currentNote !== '') {
  $parts = explode(' | ', $currentNote);
  foreach ($parts as $p) {
    $p = trim($p);
    if (stripos($p,'ขนาด:')===0)     $selSize  = trim(mb_substr($p, mb_strlen('ขนาด:')));
    elseif (stripos($p,'หวาน:')===0) $selSweet = trim(mb_substr($p, mb_strlen('หวาน:')));
    elseif (stripos($p,'น้ำแข็ง:')===0) $selIce = trim(mb_substr($p, mb_strlen('น้ำแข็ง:')));
    elseif (stripos($p,'ท็อปปิง:')===0) {
      $tp = trim(mb_substr($p, mb_strlen('ท็อปปิง:'))); if ($tp!=='') $selToppings = array_map('trim', explode(',', $tp));
    } elseif (stripos($p,'หมายเหตุ:')===0) $selFree = trim(mb_substr($p, mb_strlen('หมายเหตุ:')));
  }
}

$toppings = ['ไข่มุก','เจลลี่','พุดดิ้ง','วิปครีม'];
$isPopup = (int)($_GET['popup'] ?? 0) === 1;

/* ============ VIEW ============ */
if (!$isPopup):
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
body{ background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif; }
.containerx{ max-width:1060px; margin:28px auto; padding:0 14px; }
</style>
</head>
<body>
<div class="containerx">
<?php endif; ?>

<!-- ===== Popup / Inner Content ===== -->
<style>
/* scope ทั้งหมดไว้ใน #popup-root เพื่อไม่ชนภายนอก */
#popup-root{
  background:linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,255,255,.82));
  border-radius:20px; border:1px solid #cfe3ff; overflow:hidden;
  color:#08263f;
}
#popup-head{
  background:linear-gradient(135deg,#e7f4ff,#d5ecff);
  padding:14px 16px; border-bottom:1px solid #cfe3ff;
}
#popup-grid{ display:grid; grid-template-columns: 40% 60%; gap:0; }
#popup-grid .thumb{ width:100%; height:320px; object-fit:cover; background:#eaf4ff; }
#popup-body{ padding:16px; }
.badge-cat{ display:inline-block; background:#e9f4ff; color:#084a87; border:1px solid #bcd6f3; border-radius:999px; padding:4px 12px; font-weight:800; font-size:.85rem; }
.menu-title{ font-weight:900; margin:0; color:#08345c; }
.price-tag{ font-size:1.6rem; font-weight:900; color:#063862; background:linear-gradient(180deg,#4EC5E0,#29ABE2);
  display:inline-block; padding:6px 14px; border-radius:12px; box-shadow:0 6px 16px rgba(41,171,226,.35); }

/* ซ่อนวงกลม/สี่เหลี่ยม radio/checkbox */
#popup-root input[type="radio"], #popup-root input[type="checkbox"]{
  appearance:none; -webkit-appearance:none; -moz-appearance:none; display:none;
}
/* ปุ่มชิป */
#popup-root .chip{ display:inline-block; padding:8px 14px; margin:4px 8px 4px 0; border-radius:999px;
  font-weight:800; color:#0a3a62; background:#eef6ff; border:1px solid #cfe2ff; cursor:pointer; user-select:none; transition:.15s; }
#popup-root .chip:hover{ transform:translateY(-1px); }
#popup-root input:checked + .chip{ background:linear-gradient(180deg,#29ABE2,#4EC5E0); color:#052b47; border-color:#0e5b8a;
  box-shadow:0 6px 14px rgba(15,98,146,.25); }
.option-grid{ display:flex; flex-wrap:wrap; gap:8px 10px; }

#popup-root .sec-title{ margin:14px 0 8px; font-weight:900; color:#0D4071; letter-spacing:.2px; }
#popup-root textarea.form-control, #popup-root input.form-control{
  background:#f6fbff; border:2px solid #e2eefc; color:#0b3a61; border-radius:12px;
}
#popup-root textarea.form-control:focus, #popup-root input.form-control:focus{ box-shadow:0 0 0 .2rem rgba(41,171,226,.35); border-color:#a6cdf6; }

#popup-root .footer-actions{ border-top:1px dashed #cfe0ff; margin:16px -16px 0; padding:14px 16px; display:flex; gap:12px; }
#popup-root .btn-cancel{ background:#fff; color:#0D4071; border:2px solid #cfe0ff; font-weight:800; border-radius:12px; padding:.6rem 1rem; }
#popup-root .btn-save{ flex:1; background:linear-gradient(180deg,#1ea65a,#138b49); color:#fff; border:0; font-weight:900; letter-spacing:.2px; border-radius:12px; padding:.7rem 1rem; box-shadow:0 10px 22px rgba(20,140,75,.28); }
@media (max-width:860px){ #popup-grid{ grid-template-columns:1fr } #popup-grid .thumb{ height:220px } #popup-root .footer-actions{ flex-direction:column } }





</style>

<div id="popup-root">
  <div id="popup-head">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h4 class="menu-title mb-1"><?= htmlspecialchars($menu['name'],ENT_QUOTES,'UTF-8') ?></h4>
        <span class="badge-cat"><?= htmlspecialchars($menu['category_name'] ?? 'เมนู',ENT_QUOTES,'UTF-8') ?></span>
      </div>
      <div class="price-tag"><?= money_fmt($menu['price']) ?> ฿</div>
    </div>
  </div>

  <div id="popup-grid">
    <img src="<?= htmlspecialchars($imgSrc,ENT_QUOTES,'UTF-8') ?>" class="thumb" alt="">
    <div id="popup-body">
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
            <?php $opts=['หวานน้อย','ปกติ','หวานมาก']; foreach($opts as $i=>$sv): $id='sweet'.($i+1); $checked=($selSweet===$sv)?'checked':''; ?>
              <input type="radio" name="sweet" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="chip" for="<?= $id ?>"><?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
            <?php endforeach; ?>

            <div class="sec-title">น้ำแข็ง</div>
            <?php $opts=['ไม่ใส่น้ำแข็ง','ปกติ','เยอะ']; foreach($opts as $i=>$iv): $id='ice'.($i+1); $checked=($selIce===$iv)?'checked':''; ?>
              <input type="radio" name="ice" id="<?= $id ?>" value="<?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="chip" for="<?= $id ?>"><?= htmlspecialchars($iv,ENT_QUOTES,'UTF-8') ?></label>
            <?php endforeach; ?>

            <div class="sec-title">ขนาด</div>
            <?php $opts=['เล็ก','ธรรมดา','ใหญ่']; foreach($opts as $i=>$sv): $id='size'.($i+1); $checked=($selSize===$sv)?'checked':''; ?>
              <input type="radio" name="size" id="<?= $id ?>" value="<?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
              <label class="chip" for="<?= $id ?>"><?= htmlspecialchars($sv,ENT_QUOTES,'UTF-8') ?></label>
            <?php endforeach; ?>
          </div>

          <div class="col-md-6">
            <div class="sec-title">ท็อปปิง (เลือกได้หลายอย่าง)</div>
            <div class="option-grid">
              <?php foreach($toppings as $i=>$tp): $id='tp'.($i+1); $checked=in_array($tp,$selToppings,true)?'checked':''; ?>
                <input type="checkbox" name="toppings[]" id="<?= $id ?>" value="<?= htmlspecialchars($tp,ENT_QUOTES,'UTF-8') ?>" <?= $checked ?>>
                <label class="chip" for="<?= $id ?>"><?= htmlspecialchars($tp,ENT_QUOTES,'UTF-8') ?></label>
              <?php endforeach; ?>
            </div>

            <div class="sec-title">หมายเหตุเพิ่มเติม</div>
            <textarea class="form-control" name="note_free" rows="3" placeholder="เช่น ไม่ใส่ฝา งดหลอด ฯลฯ"><?= htmlspecialchars($selFree,ENT_QUOTES,'UTF-8') ?></textarea>

            <div class="sec-title">จำนวน</div>
            <input type="number" class="form-control" name="qty" value="<?= (int)$currentQty ?>" min="1" style="max-width:160px">
          </div>
        </div>

        <input type="hidden" name="note" id="note">
        <div class="footer-actions">
          <?php if(!$isPopup): ?>
            <a href="front_store.php" class="btn btn-cancel">ยกเลิก</a>
          <?php else: ?>
            <button type="button" class="btn btn-cancel" onclick="window.parent?.document.getElementById('menuModalClose')?.click()">ยกเลิก</button>
          <?php endif; ?>
          <button type="submit" class="btn btn-save"><?= $editMode ? 'บันทึกการแก้ไข' : 'เพิ่มในตะกร้า' ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// เผื่อเปิดแบบหน้าเดี่ยว: รวม note ก่อน submit
document.getElementById('menuForm')?.addEventListener('submit', function(){
  const root = document;
  const pick = name => (root.querySelector(`input[name="${name}"]:checked`)||{}).value || '';
  const parts = [];
  const size = pick('size'), sweet = pick('sweet'), ice = pick('ice');
  if(size)  parts.push('ขนาด: '+size);
  if(sweet) parts.push('หวาน: '+sweet);
  if(ice)   parts.push('น้ำแข็ง: '+ice);
  const tops = Array.from(root.querySelectorAll('input[name="toppings[]"]:checked')).map(x=>x.value);
  const free = (root.querySelector('textarea[name="note_free"]')?.value || '').trim();
  if(tops.length) parts.push('ท็อปปิง: '+tops.join(', '));
  if(free) parts.push('หมายเหตุ: '+free);
  (root.getElementById('note')||{}).value = parts.join(' | ');
});
</script>

<?php if (!$isPopup): ?>
</div>
</body>
</html>
<?php endif; ?>

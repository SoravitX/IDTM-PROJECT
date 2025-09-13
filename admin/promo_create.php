<?php
// admin/promo_create.php — สร้างโปรโมชัน + ผูกกับหลายเมนู
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
// ถ้าจะจำกัดเฉพาะ admin ให้เปิดบรรทัดนี้
// if (($_SESSION['role'] ?? '') !== 'admin') { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function dtValue(?string $dt): string {
  if (!$dt) return '';
  $t = strtotime($dt);
  return $t ? date('Y-m-d\TH:i', $t) : '';
}

$message = '';
$msgClass = 'success';

/* ===== POST: สร้างโปรโมชันใหม่ ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $name           = trim((string)($_POST['name'] ?? ''));
  $scope          = $_POST['scope'] === 'ORDER' ? 'ORDER' : 'ITEM';
  $discount_type  = $_POST['discount_type'] === 'FIXED' ? 'FIXED' : 'PERCENT';
  $discount_value = (float)($_POST['discount_value'] ?? 0);
  $min_order_total= (isset($_POST['min_order_total']) && $_POST['min_order_total'] !== '') ? (float)$_POST['min_order_total'] : null;
  $max_discount   = (isset($_POST['max_discount']) && $_POST['max_discount'] !== '') ? (float)$_POST['max_discount'] : null;
  $usage_limit    = (isset($_POST['usage_limit']) && $_POST['usage_limit'] !== '') ? (int)$_POST['usage_limit'] : null;
  $is_active      = isset($_POST['is_active']) ? 1 : 0;

  $start_raw      = trim((string)($_POST['start_at'] ?? ''));   // datetime-local
  $end_raw        = trim((string)($_POST['end_at'] ?? ''));
  $start_at       = str_replace('T', ' ', $start_raw);
  $end_at         = str_replace('T', ' ', $end_raw);

  // รายการเมนู (เฉพาะ scope = ITEM)
  $menu_ids = [];
  if ($scope === 'ITEM') {
    $menu_ids = array_values(array_unique(array_map('intval', (array)($_POST['menu_ids'] ?? []))));
  }

  // === Validation เบื้องต้น ===
  if ($name === '') {
    $message = 'กรุณากรอกชื่อโปรโมชัน'; $msgClass = 'danger';
  } elseif ($discount_value <= 0) {
    $message = 'ส่วนลดต้องมากกว่า 0'; $msgClass = 'danger';
  } elseif ($start_raw === '' || $end_raw === '') {
    $message = 'กรุณาระบุช่วงเวลาเริ่ม/สิ้นสุด'; $msgClass = 'danger';
  } elseif (strtotime($end_at) <= strtotime($start_at)) {
    $message = 'เวลาสิ้นสุดต้องหลังเวลาเริ่ม'; $msgClass = 'danger';
  } elseif ($scope === 'ITEM' && count($menu_ids) === 0) {
    $message = 'เลือกเมนูที่จะเข้าร่วมโปรโมชันอย่างน้อย 1 รายการ'; $msgClass = 'danger';
  } else {
    // สร้างโปรโมชัน
   $stmt = $conn->prepare("
  INSERT INTO promotions
    (name, scope, discount_type, discount_value, min_order_total, max_discount,
     start_at, end_at, is_active, usage_limit, used_count, created_at, updated_at)
  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
");

// $min, $max, $limit กำหนดไว้ก่อนหน้าแล้ว และอาจเป็น NULL ได้
$min   = $min_order_total;
$max   = $max_discount;
$limit = $usage_limit;

// ✅ ใช้ชนิดให้ตรงกับตัวแปร 10 ตัว: sss ddd ss ii
$stmt->bind_param(
  'sssdddssii',
  $name,           // s
  $scope,          // s
  $discount_type,  // s
  $discount_value, // d
  $min,            // d (nullable)
  $max,            // d (nullable)
  $start_at,       // s
  $end_at,         // s
  $is_active,      // i
  $limit           // i (nullable)
);

    $stmt->execute();
    $promo_id = (int)$stmt->insert_id;
    $stmt->close();

    // ผูกเมนูกับโปรโมชัน (เฉพาะ ITEM)
    if ($scope === 'ITEM' && $promo_id > 0 && $menu_ids) {
      $ins = $conn->prepare("INSERT IGNORE INTO promotion_items (promo_id, menu_id) VALUES (?, ?)");
      foreach ($menu_ids as $mid) {
        $ins->bind_param('ii', $promo_id, $mid);
        $ins->execute();
      }
      $ins->close();
    }

    $message = 'สร้างโปรโมชันเรียบร้อย #' . $promo_id;
    $msgClass = 'success';
  }
}

/* ===== Data: เมนูทั้งหมด (active) สำหรับเลือกผูกโปรโมชัน ===== */
$menus = $conn->query("
  SELECT m.menu_id, m.name, m.price, c.category_name
  FROM menu m
  LEFT JOIN categories c ON m.category_id = c.category_id
  WHERE m.is_active = 1
  ORDER BY c.category_name, m.name
");

/* ===== โปรโมชันล่าสุด (โชว์เป็นรายการสรุปสั้น ๆ) ===== */
$recent = $conn->query("
  SELECT p.promo_id, p.name, p.scope, p.discount_type, p.discount_value,
         p.start_at, p.end_at, p.is_active,
         (SELECT COUNT(*) FROM promotion_items pi WHERE pi.promo_id = p.promo_id) AS item_count
  FROM promotions p
  ORDER BY p.promo_id DESC
  LIMIT 15
");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Admin • สร้างโปรโมชัน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
  href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --deep:#0D4071; --ocean:#4173BD; --sky:#29ABE2; --andaman:#0094B3; --sritrang:#BBB4D8;
}
body{background:linear-gradient(135deg,var(--deep),var(--ocean));color:#fff;font-family:"Segoe UI",Tahoma}
.wrap{max-width:1100px;margin:26px auto;padding:0 14px}
.cardx{background:rgba(255,255,255,.95);color:#0b2746;border:1px solid #d9e6ff;border-radius:16px;box-shadow:0 12px 28px rgba(0,0,0,.22)}
.badge-chip{background:#eaf4ff;color:#0D4071;border:1px solid #cfe2ff;border-radius:999px;padding:.2rem .6rem;font-weight:800}
.btn-main{background:var(--andaman);border:1px solid #063d63;font-weight:800;color:#fff}
.btn-main:hover{background:var(--sky);color:#063d63}
.form-control, .custom-select{border:2px solid var(--ocean); border-radius:10px}
.form-control:focus{box-shadow:0 0 0 .2rem rgba(41,171,226,.35)}
.menu-list{max-height:320px;overflow:auto;border:1px solid #e3ecff;border-radius:10px}
.menu-item{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-bottom:1px dashed #e8f1ff}
.menu-item:last-child{border-bottom:0}
.muted{color:#6b7280}
</style>
</head>
<body>
<div class="wrap">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0 font-weight-bold">สร้างโปรโมชัน</h4>
    <div>
      <a href="adminmenu.php" class="btn btn-sm btn-outline-light mr-2">เมนูแอดมิน</a>
      <a href="../SelectRole/role.php" class="btn btn-sm btn-outline-light mr-2">หน้าหลัก</a>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <?php if($message!==''): ?>
    <div class="alert alert-<?= h($msgClass) ?>"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- ฟอร์มสร้างโปรโมชัน -->
  <div class="cardx p-3 mb-3">
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>ชื่อโปรโมชัน</label>
          <input type="text" name="name" class="form-control" required placeholder="เช่น ลดพิเศษ 10%">
        </div>
        <div class="form-group col-md-3">
          <label>ประเภทส่วนลด</label>
          <select name="discount_type" class="custom-select">
            <option value="PERCENT">เปอร์เซ็นต์ (%)</option>
            <option value="FIXED">จำนวนเงิน (บาท)</option>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label>ค่าลด</label>
          <input type="number" name="discount_value" step="0.01" min="0" class="form-control" required placeholder="เช่น 10 หรือ 20.00">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-3">
          <label>ขอบเขต (Scope)</label>
          <select name="scope" id="scope" class="custom-select">
            <option value="ITEM">เฉพาะสินค้า (ITEM)</option>
            <option value="ORDER">ทั้งบิล (ORDER)</option>
          </select>
        </div>
        <div class="form-group col-md-3">
          <label>ยอดสั่งซื้อขั้นต่ำ (ถ้ามี)</label>
          <input type="number" name="min_order_total" step="0.01" min="0" class="form-control" placeholder="เช่น 100.00">
        </div>
        <div class="form-group col-md-3">
          <label>ส่วนลดสูงสุด (ถ้ามี)</label>
          <input type="number" name="max_discount" step="0.01" min="0" class="form-control" placeholder="เช่น 50.00">
        </div>
        <div class="form-group col-md-3">
          <label>จำกัดจำนวนใช้ (ถ้ามี)</label>
          <input type="number" name="usage_limit" min="1" class="form-control" placeholder="เช่น 100">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group col-md-3">
          <label>เริ่ม</label>
          <input type="datetime-local" name="start_at" class="form-control" required>
        </div>
        <div class="form-group col-md-3">
          <label>สิ้นสุด</label>
          <input type="datetime-local" name="end_at" class="form-control" required>
        </div>
        <div class="form-group col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
            <label class="form-check-label" for="is_active">เปิดใช้งานทันที</label>
          </div>
        </div>
      </div>

      <!-- เลือกเมนู (เฉพาะ ITEM) -->
      <div id="itemScopeBox" class="mb-2">
        <label class="mb-1">เลือกเมนูที่จะเข้าร่วม</label>
        <input type="text" id="menuSearch" class="form-control mb-2" placeholder="ค้นหาเมนู…">
        <div class="menu-list">
          <?php if($menus && $menus->num_rows > 0): ?>
            <?php while($m = $menus->fetch_assoc()): ?>
              <label class="menu-item">
                <span>
                  <strong><?= h($m['name']) ?></strong>
                  <small class="muted"> • หมวด: <?= h($m['category_name'] ?? '-') ?> • ราคา <?= number_format((float)$m['price'],2) ?>฿</small>
                </span>
                <span>
                  <input type="checkbox" name="menu_ids[]" value="<?= (int)$m['menu_id'] ?>">
                </span>
              </label>
            <?php endwhile; ?>
          <?php else: ?>
            <div class="p-2 text-muted">ยังไม่มีเมนู</div>
          <?php endif; ?>
        </div>
        <small class="text-muted">* เลือกได้หลายรายการ</small>
      </div>

      <button class="btn btn-main">+ สร้างโปรโมชัน</button>
    </form>
  </div>

  <!-- โปรโมชันล่าสุด -->
  <div class="cardx p-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="h6 mb-0 font-weight-bold">โปรโมชันล่าสุด</div>
      <span class="badge badge-chip">แสดง 15 รายการล่าสุด</span>
    </div>

    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <th>ชื่อ</th>
            <th style="width:90px">Scope</th>
            <th style="width:100px">ประเภทส่วนลด</th>
            <th style="width:120px">ค่า</th>
            <th style="width:160px">เริ่ม</th>
            <th style="width:160px">สิ้นสุด</th>
            <th style="width:90px">สถานะ</th>
            <th style="width:120px" class="text-right">จำนวนเมนู</th>
            <th style="width:110px" class="text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if($recent && $recent->num_rows>0): ?>
            <?php while($p=$recent->fetch_assoc()): ?>
              <tr>
                <td><?= (int)$p['promo_id'] ?></td>
                <td><?= h($p['name']) ?></td>
                <td><span class="badge badge-info"><?= h($p['scope']) ?></span></td>
                <td><?= h($p['discount_type']) ?></td>
                <td>
                  <?php if($p['discount_type']==='PERCENT'): ?>
                    <?= number_format((float)$p['discount_value'],2) ?>%
                  <?php else: ?>
                    <?= number_format((float)$p['discount_value'],2) ?> ฿
                  <?php endif; ?>
                </td>
                <td><?= h($p['start_at']) ?></td>
                <td><?= h($p['end_at']) ?></td>
                <td>
                  <?php if((int)$p['is_active']===1): ?>
                    <span class="badge badge-success">เปิด</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">ปิด</span>
                  <?php endif; ?>
                </td>
                <td class="text-right"><?= (int)$p['item_count'] ?> รายการ</td>
                <td class="text-right">
                  <a class="btn btn-sm btn-outline-primary" href="promo_detail.php?id=<?= (int)$p['promo_id'] ?>">รายละเอียด</a>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="10" class="text-center text-muted">ยังไม่มีโปรโมชัน</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// toggle แสดงกล่องเลือกเมนูเมื่อ scope = ITEM
const scopeSel = document.getElementById('scope');
const itemBox  = document.getElementById('itemScopeBox');
function toggleScopeBox(){
  if (!scopeSel || !itemBox) return;
  itemBox.style.display = (scopeSel.value === 'ITEM') ? '' : 'none';
}
scopeSel?.addEventListener('change', toggleScopeBox);
toggleScopeBox();

// ค้นหาเมนูในกล่องเลือก
const menuSearch = document.getElementById('menuSearch');
menuSearch?.addEventListener('input', e=>{
  const q = (e.target.value || '').toLowerCase().trim();<table class="table table-sm mb-0">
  <thead>
    <tr>
      <th style="width:70px">#</th>
      <th>ชื่อ</th>
      <th style="width:90px">Scope</th>
      <th style="width:100px">ประเภทส่วนลด</th>
      <th style="width:120px">ค่า</th>
      <th style="width:160px">เริ่ม</th>
      <th style="width:160px">สิ้นสุด</th>
      <th style="width:90px">สถานะ</th>
      <th style="width:120px" class="text-right">จำนวนเมนู</th>
      <th style="width:110px" class="text-right">จัดการ</th>
    </tr>
    
  </thead>
  <tbody>
    <?php while($p=$recent->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$p['promo_id'] ?></td>
        <td><?= h($p['name']) ?></td>
        <td><span class="badge badge-info"><?= h($p['scope']) ?></span></td>
        <td><?= h($p['discount_type']) ?></td>
        <td>
          <?php if($p['discount_type']==='PERCENT'): ?>
            <?= number_format((float)$p['discount_value'],2) ?>%
          <?php else: ?>
            <?= number_format((float)$p['discount_value'],2) ?> ฿
          <?php endif; ?>
        </td>
        <td><?= h($p['start_at']) ?></td>
        <td><?= h($p['end_at']) ?></td>
        <td>
          <?php if((int)$p['is_active']===1): ?>
            <span class="badge badge-success">เปิด</span>
          <?php else: ?>
            <span class="badge badge-secondary">ปิด</span>
          <?php endif; ?>
        </td>
        <td class="text-right"><?= (int)$p['item_count'] ?> รายการ</td>
        <td class="text-right">
          <a class="btn btn-sm btn-outline-primary" href="promo_detail.php?id=<?= (int)$p['promo_id'] ?>">
            รายละเอียด
          </a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>

  document.querySelectorAll('.menu-item').forEach(li=>{
    const txt = li.textContent.toLowerCase();
    li.style.display = txt.includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>

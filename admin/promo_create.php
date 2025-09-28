<?php
// admin/promo_create.php — สร้างโปรโมชัน + ผูกกับหลายเมนู (Dark Teal Theme)
// ✅ แต่ง UI + เติมฟิลด์ที่ backend ต้องใช้ (scope/min/max/usage) + แก้ JS scope id
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
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
  $scope          = ($_POST['scope'] ?? 'ITEM') === 'ORDER' ? 'ORDER' : 'ITEM';
  $discount_type  = ($_POST['discount_type'] ?? 'PERCENT') === 'FIXED' ? 'FIXED' : 'PERCENT';
  $discount_value = (float)($_POST['discount_value'] ?? 0);
  $min_order_total= (isset($_POST['min_order_total']) && $_POST['min_order_total'] !== '') ? (float)$_POST['min_order_total'] : null;
  $max_discount   = (isset($_POST['max_discount']) && $_POST['max_discount'] !== '') ? (float)$_POST['max_discount'] : null;
  $usage_limit    = (isset($_POST['usage_limit']) && $_POST['usage_limit'] !== '') ? (int)$_POST['usage_limit'] : null;
  $is_active      = isset($_POST['is_active']) ? 1 : 0;

  $start_raw      = trim((string)($_POST['start_at'] ?? ''));
  $end_raw        = trim((string)($_POST['end_at'] ?? ''));
  $start_at       = str_replace('T', ' ', $start_raw);
  $end_at         = str_replace('T', ' ', $end_raw);

  $menu_ids = [];
  if ($scope === 'ITEM') {
    $menu_ids = array_values(array_unique(array_map('intval', (array)$_POST['menu_ids'] ?? [])));
  }

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
    $stmt = $conn->prepare("
      INSERT INTO promotions
        (name, scope, discount_type, discount_value, min_order_total, max_discount,
         start_at, end_at, is_active, usage_limit, used_count, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $min   = $min_order_total;
    $max   = $max_discount;
    $limit = $usage_limit;

    $stmt->bind_param('sssdddssii',
      $name, $scope, $discount_type, $discount_value, $min, $max, $start_at, $end_at, $is_active, $limit
    );
    $stmt->execute();
    $promo_id = (int)$stmt->insert_id;
    $stmt->close();

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

/* ===== Data: เมนูทั้งหมด (active) ===== */
$menus = $conn->query("
  SELECT m.menu_id, m.name, m.price, c.category_name
  FROM menu m
  LEFT JOIN categories c ON m.category_id = c.category_id
  WHERE m.is_active = 1
  ORDER BY c.category_name, m.name
");

/* ===== โปรโมชันล่าสุด ===== */
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
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg-grad1:#222831; --bg-grad2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --ink:#F4F7F8;
  --brand-900:#EEEEEE; --brand-700:#BFC6CC;
  --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
  --ok:#2ecc71; --danger:#e53935;
  --shadow:0 14px 32px rgba(0,0,0,.42);
}
html,body{height:100%}
body{
  background:
    radial-gradient(800px 320px at 100% -10%, rgba(39,200,207,.16), transparent 60%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{max-width:1100px;margin:26px auto;padding:0 14px}
.h4,.h5,h4,h5{ color:var(--brand-900) }

/* Card */
.cardx{
  background:linear-gradient(180deg,var(--surface),var(--surface-2));
  color:var(--ink); border:1px solid rgba(255,255,255,.06);
  border-radius:16px; box-shadow:var(--shadow);
}
.card-head{
  padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.08);
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
  color:var(--brand-700); font-weight:800;
}

/* Badges & Buttons */
.badge-chip{
  background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.04));
  color:var(--brand-900); border:1px solid rgba(255,255,255,.12);
  border-radius:999px; padding:.25rem .6rem; font-weight:800
}
.btn-main{
  background:linear-gradient(180deg, var(--brand-500), #07949B);
  border:0; font-weight:900; color:#061217; border-radius:12px;
  box-shadow:0 10px 26px rgba(0,173,181,.25);
}
.btn-outline-light{font-weight:800;border-radius:12px;border-color:rgba(255,255,255,.25);color:var(--text-normal)}
.btn-outline-light:hover{background:rgba(255,255,255,.06)}

/* ===== ปุ่ม "รายละเอียด" โทนสีน้ำเงิน ===== */
.btn-detail{
  background: linear-gradient(180deg, #3aa3ff, #1f7ee8);
  color: #ffffff;
  border: 0;
  border-radius: 10px;
  font-weight: 900;
  padding: .25rem .6rem;
  box-shadow: 0 10px 26px rgba(31,126,232,.28);
}
.btn-detail:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff; }
.btn-detail:focus{ outline:3px solid rgba(31,126,232,.35); outline-offset:2px; }

/* Form */
label{ color:var(--brand-700); font-weight:700 }
.form-control,.custom-select{
  color:var(--text-strong); background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.10); border-radius:12px;
}
.form-control::placeholder{ color:#9aa3ab }
.form-control:focus,.custom-select:focus{
  border-color:var(--brand-500);
  box-shadow:0 0 0 .2rem rgba(0,173,181,.25);
  background:#2F373F;
}

/* Menu picker */
.menu-list{
  max-height:340px; overflow:auto; border:1px solid rgba(255,255,255,.08);
  border-radius:12px; background:#1f252b;
}
.menu-item{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 12px; border-bottom:1px dashed rgba(255,255,255,.08); color:var(--text-normal)
}
.menu-item:last-child{border-bottom:0}
.menu-item strong{color:var(--brand-300)}
.muted{color:var(--text-muted)}

/* Table */
.table thead th{
  background:#222a31; color:var(--brand-300);
  border-bottom:2px solid rgba(255,255,255,.08); font-weight:800
}
.table td,.table th{ border-color:rgba(255,255,255,.06)!important; color:var(--text-normal) }
.table tbody tr:hover td{ background:#20262d; color:var(--text-strong) }

.alert-success{background:rgba(46,204,113,.12);color:#7ee2a6;border:1px solid rgba(46,204,113,.35)}
.alert-danger {background:rgba(229,57,53,.12); color:#ff9f9c;border:1px solid rgba(229,57,53,.35)}
</style>
</head>
<body>
<div class="wrap">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0 font-weight-bold"><i class="bi bi-ticket-perforated"></i> สร้างโปรโมชัน</h4>
    <div>
      <a href="adminmenu.php" class="btn btn-sm btn-outline-light mr-2"><i class="bi bi-gear"></i> เมนูแอดมิน</a>
      <a href="../logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <?php if($message!==''): ?>
    <div class="alert alert-<?= h($msgClass) ?>"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- ฟอร์มสร้างโปรโมชัน -->
  <div class="cardx mb-3">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div><i class="bi bi-sliders"></i> ตั้งค่าโปรโมชัน</div>
      <span class="badge-chip">เปอร์เซ็นต์/บาท • ITEM/ORDER</span>
    </div>
    <div class="p-3">
      <form method="post">
        <input type="hidden" name="action" value="create">

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>ชื่อโปรโมชัน</label>
            <input type="text" name="name" class="form-control" required placeholder="เช่น ลดพิเศษ 10%">
          </div>

          <div class="form-group col-md-3">
            <label>Scope</label>
            <select name="scope" id="scope" class="custom-select">
              <option value="ITEM" selected>ITEM — ลดเฉพาะเมนูที่เลือก</option>
              <option value="ORDER">ORDER — ลดทั้งบิล</option>
            </select>
          </div>

          <div class="form-group col-md-3">
            <label>ประเภทส่วนลด</label>
            <select name="discount_type" class="custom-select">
              <option value="PERCENT">เปอร์เซ็นต์ (%)</option>
              <option value="FIXED">จำนวนเงิน (บาท)</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label>ค่าลด</label>
            <input type="number" name="discount_value" step="0.01" min="0" class="form-control" required placeholder="เช่น 10 หรือ 20.00">
          </div>

          <div class="form-group col-md-3">
            <label>ส่วนลดสูงสุด (บาท)</label>
            <input type="number" name="max_discount" step="0.01" min="0" class="form-control" placeholder="ไม่ระบุก็ได้">
          </div>

          <div class="form-group col-md-3">
            <label>ใช้ได้สูงสุด (ครั้ง)</label>
            <input type="number" name="usage_limit" step="1" min="0" class="form-control" placeholder="ไม่ระบุก็ได้">
          </div>

          <div class="form-group col-md-3">
            <label>ขั้นต่ำทั้งบิล (ORDER)</label>
            <input type="number" name="min_order_total" step="0.01" min="0" class="form-control" placeholder="ใช้เมื่อ Scope=ORDER">
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
          <label class="mb-1"><i class="bi bi-list-check"></i> เลือกเมนูที่จะเข้าร่วม</label>
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
          <small class="text-muted d-block mt-1">* เลือกได้หลายรายการ</small>
        </div>

        <button class="btn btn-main"><i class="bi bi-plus-circle"></i> สร้างโปรโมชัน</button>
      </form>
    </div>
  </div>

  <!-- โปรโมชันล่าสุด -->
  <div class="cardx">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div><i class="bi bi-clock-history"></i> โปรโมชันล่าสุด</div>
      <span class="badge-chip">แสดง 15 รายการล่าสุด</span>
    </div>
    <div class="p-3">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th style="width:70px">#</th>
              <th>ชื่อ</th>
              <th style="width:90px">Scope</th>
              <th style="width:120px">ประเภทส่วนลด</th>
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
                  <td><span class="badge-chip"><?= h($p['scope']) ?></span></td>
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
                      <span class="badge-chip" style="color:#7ee2a6;border-color:rgba(46,204,113,.35)">เปิด</span>
                    <?php else: ?>
                      <span class="badge-chip" style="color:#ccc">ปิด</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-right"><?= (int)$p['item_count'] ?> รายการ</td>
                  <td class="text-right">
                    <!-- เปลี่ยนเป็นปุ่มสีน้ำเงิน -->
                    <a class="btn btn-sm btn-detail" href="promo_detail.php?id=<?= (int)$p['promo_id'] ?>">
                      รายละเอียด
                    </a>
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

</div>

<script>
// toggle กล่องเลือกเมนูเมื่อ scope = ITEM
const scopeSel = document.getElementById('scope');
const itemBox  = document.getElementById('itemScopeBox');
function toggleScopeBox(){
  if (!scopeSel || !itemBox) return;
  itemBox.style.display = (scopeSel.value === 'ITEM') ? '' : 'none';
}
scopeSel?.addEventListener('change', toggleScopeBox);
toggleScopeBox();

// ค้นหาเมนู
const menuSearch = document.getElementById('menuSearch');
menuSearch?.addEventListener('input', e=>{
  const q = (e.target.value || '').toLowerCase().trim();
  document.querySelectorAll('.menu-item').forEach(li=>{
    const txt = li.textContent.toLowerCase();
    li.style.display = txt.includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>

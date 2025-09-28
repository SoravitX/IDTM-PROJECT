<?php
// admin/promo_detail.php — ดู/แก้ไขโปรโมชัน + ผูก/ถอดเมนู + เปิด/ปิดโปร (Dark Teal Theme)
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function baht($n){ return number_format((float)$n, 2); }

$promo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($promo_id <= 0) { header("Location: promo_list.php"); exit; }

$msg = ''; $cls = 'success';

/* ========== โหลดข้อมูลโปรโมชัน ========== */
$stmt = $conn->prepare("SELECT promo_id, name, scope, discount_type, discount_value, min_order_total, max_discount, start_at, end_at, is_active FROM promotions WHERE promo_id=?");
$stmt->bind_param('i', $promo_id);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$promo) { header("Location: promo_list.php"); exit; }

/* ========== จัดการ POST Actions ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // (1) Toggle เปิด/ปิดโปรโมชัน
  if ($action === 'toggle_active') {
    $new = (int)!((int)$promo['is_active']);
    $stmt = $conn->prepare("UPDATE promotions SET is_active=?, updated_at=NOW() WHERE promo_id=?");
    $stmt->bind_param('ii', $new, $promo_id);
    $stmt->execute();
    $stmt->close();
    $promo['is_active'] = $new;
    $msg = 'อัปเดตสถานะโปรโมชันเรียบร้อย'; $cls = 'success';
  }

  // (2) เพิ่มเมนูเข้ากับโปรโมชัน (ใช้ UNIQUE KEY uk_promo_menu กันซ้ำ)
  if ($action === 'add_menu') {
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    if ($menu_id > 0) {
      $stmt = $conn->prepare("INSERT IGNORE INTO promotion_items (promo_id, menu_id) VALUES (?, ?)");
      $stmt->bind_param('ii', $promo_id, $menu_id);
      $stmt->execute();
      $stmt->close();
      $msg = 'เพิ่มเมนูเข้าโปรโมชันแล้ว'; $cls = 'success';
    } else {
      $msg = 'กรุณาเลือกเมนูให้ถูกต้อง'; $cls = 'danger';
    }
  }

  // (3) เอาเมนูออกจากโปรโมชัน
  if ($action === 'remove_menu') {
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    if ($menu_id > 0) {
      $stmt = $conn->prepare("DELETE FROM promotion_items WHERE promo_id=? AND menu_id=?");
      $stmt->bind_param('ii', $promo_id, $menu_id);
      $stmt->execute();
      $stmt->close();
      $msg = 'นำเมนูออกจากโปรโมชันแล้ว'; $cls = 'success';
    }
  }
}

/* ========== ดึงเมนูที่อยู่ในโปรนี้แล้ว ========== */
$menus_in = [];
$stmt = $conn->prepare("
  SELECT m.menu_id, m.name, m.price, m.image, c.category_name
  FROM promotion_items pi
  JOIN menu m ON m.menu_id = pi.menu_id
  LEFT JOIN categories c ON c.category_id = m.category_id
  WHERE pi.promo_id=?
  ORDER BY m.name
");
$stmt->bind_param('i', $promo_id);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $menus_in[] = $r;
$stmt->close();

/* ========== ดึงเมนูที่ยังไม่อยู่ในโปรนี้ (ตัวเลือกสำหรับเพิ่ม) ========== */
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
  $stmt = $conn->prepare("
    SELECT m.menu_id, m.name
    FROM menu m
    WHERE m.menu_id NOT IN (SELECT menu_id FROM promotion_items WHERE promo_id = ?)
      AND m.name LIKE ?
    ORDER BY m.name
  ");
  $kw = '%'.$q.'%';
  $stmt->bind_param('is', $promo_id, $kw);
} else {
  $stmt = $conn->prepare("
    SELECT m.menu_id, m.name
    FROM menu m
    WHERE m.menu_id NOT IN (SELECT menu_id FROM promotion_items WHERE promo_id = ?)
    ORDER BY m.name
  ");
  $stmt->bind_param('i', $promo_id);
}
$stmt->execute();
$menus_not_in = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายละเอียดโปรโมชัน • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;

  --bg-grad1:#222831; /* background */
  --bg-grad2:#393E46;

  --surface:#1C2228;  /* cards */
  --surface-2:#232A31;
  --surface-3:#2B323A;

  --ink:#F4F7F8; --ink-muted:#CFEAED;

  --brand-900:#EEEEEE; --brand-700:#BFC6CC;
  --brand-500:#00ADB5; /* accent */
  --brand-400:#27C8CF; --brand-300:#73E2E6;

  --ok:#2ecc71; --danger:#e53935;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

html,body{height:100%}
body{
  margin:0;
  background:
    radial-gradient(900px 360px at 110% -10%, rgba(39,200,207,.18), transparent 65%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial;
}
.wrap{max-width:1100px; margin:22px auto; padding:0 14px}

/* Topbar */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(28,34,40,.85); border:1px solid rgba(255,255,255,.08);
  box-shadow:var(--shadow); backdrop-filter: blur(6px);
}
.topbar .btn{ border-radius:12px }

/* Cards */
.cardx{
  background: linear-gradient(180deg,var(--surface),var(--surface-2));
  color: var(--ink);
  border:1px solid rgba(255,255,255,.06);
  border-radius:16px;
  box-shadow: var(--shadow);
  padding:16px;
}
.cardx .muted{ color:var(--text-muted) }

/* Pills / badges */
.badge-pillx{
  display:inline-block; padding:.35rem .7rem; border-radius:999px;
  background:linear-gradient(180deg,rgba(255,255,255,.08),rgba(255,255,255,.04));
  border:1px solid rgba(255,255,255,.15);
  color:var(--brand-900); font-weight:800
}
.badge-pill-danger{
  background:rgba(229,57,53,.12); border-color:rgba(229,57,53,.35); color:#ff9f9c
}

/* Buttons */
.btn-ghost{
  background: linear-gradient(180deg, var(--brand-500), #07949B);
  border:0; color:#061217; font-weight:900; border-radius:12px;
  box-shadow:0 10px 26px rgba(0,173,181,.25);
}
.btn-ghost:hover{ filter:brightness(1.05) }
.btn-toggle.btn-success{
  background:linear-gradient(180deg,#2ecc71,#239e57); border:0; color:#061217; border-radius:12px
}
.btn-toggle.btn-danger{
  background:linear-gradient(180deg,#ff6b6b,#e53935); border:0; color:#fff; border-radius:12px
}
.btn-outline-light{
  color:var(--text-normal); border-color:rgba(255,255,255,.25); border-radius:12px; font-weight:800
}
.btn-outline-light:hover{ background:rgba(255,255,255,.06) }
.btn-primary{
  background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border:0; border-radius:12px; font-weight:900
}

/* Inputs */
.searchbox, .form-control, .custom-select{
  background: var(--surface-3);
  border:1.5px solid rgba(255,255,255,.12);
  border-radius:12px;
  color:var(--text-strong);
}
.searchbox::placeholder, .form-control::placeholder{ color:#9aa3ab }
.searchbox:focus, .form-control:focus, .custom-select:focus{
  border-color: var(--brand-500);
  box-shadow: 0 0 0 .2rem rgba(0,173,181,.25);
  background:#2F373F;
}

/* Table */
.table thead th{
  background:#222a31; color:var(--brand-300);
  border-bottom:2px solid rgba(255,255,255,.08); font-weight:800
}
.table td, .table th{
  border-color:rgba(255,255,255,.06)!important; color:var(--text-normal); vertical-align: middle !important;
}
.table tbody tr:hover td{ background:#20262d; color:var(--text-strong) }

/* Alerts */
.alert-success{background:rgba(46,204,113,.12);color:#7ee2a6;border:1px solid rgba(46,204,113,.35); border-radius:12px}
.alert-danger {background:rgba(229,57,53,.12); color:#ff9f9c;border:1px solid rgba(229,57,53,.35); border-radius:12px}

/* Minor helpers */
.h-title{ color:var(--brand-900); font-weight:900 }
.small-note{ color:var(--text-muted) }
/* ===== Force white text in <select> dropdowns on dark theme ===== */
select,
.custom-select,
.form-control {
  color: var(--text-strong) !important;        /* ข้อความในช่อง */
}

/* ข้อความภายในรายการตัวเลือก */
select option,
.custom-select option,
select optgroup {
  color: var(--text-strong) !important;        /* ให้เป็นสีขาว */
  background-color: var(--surface-3) !important; /* พื้นหลังดรอปดาวน์ */
}

/* เมื่อเลือกอยู่ (บางเบราว์เซอร์รองรับ) */
select option:checked,
.custom-select option:checked {
  color: #fff !important;
  background: linear-gradient(180deg, #2a9aa1, #137d84) !important;
}

/* รายการที่ disabled ให้ดูซีดลงหน่อย */
select option[disabled],
.custom-select option[disabled] {
  color: var(--text-muted) !important;
}

/* Fix บน WebKit/Chromium (Edge/Chrome) ให้ตัวอักษรขาวชัด */
@supports (-webkit-appearance: none) {
  select,
  .custom-select {
    -webkit-text-fill-color: var(--text-strong) !important;
  }
}

/* ถ้าใช้ size / multiple (list ยาวแบบในภาพ) ให้ช่องสูงอ่านง่ายขึ้น */
select[size],
select[multiple] {
  background-color: var(--surface-3);
  border-color: rgba(255,255,255,.12);
  color: var(--text-strong);
}

</style>
</head>
<body>
<div class="wrap">

  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div>
      <div class="h5 m-0 h-title">รายละเอียดโปรโมชัน • PSU Blue Cafe</div>
      <small class="small-note">แก้ไขโปร, ผูกเมนู, เปิด/ปิดการใช้งาน</small>
    </div>
    <div class="d-flex align-items-center">
      <a href="promo_create.php" class="btn btn-outline-light btn-sm mr-2">← กลับรายการโปรโมชัน</a>
      <a href="adminmenu.php" class="btn btn-outline-light btn-sm mr-2">ไปหน้า Admin</a>
      <a href="../front_store/front_store.php" class="btn btn-ghost btn-sm">ไปหน้าร้าน</a>
    </div>
  </div>

  <div class="cardx mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap">
      <div class="mr-3">
        <div class="h4 mb-2 h-title"><?= h($promo['name']) ?></div>
        <div class="mb-2">
          <span class="badge-pillx">Scope: <?= h($promo['scope']) ?></span>
          <span class="badge-pillx">Type: <?= h($promo['discount_type']) ?></span>
          <span class="badge-pillx">Value: <?= h($promo['discount_type']==='PERCENT' ? baht($promo['discount_value']).' %' : baht($promo['discount_value']).' ฿') ?></span>
          <?php if($promo['min_order_total']!==null): ?>
            <span class="badge-pillx">Min: <?= baht($promo['min_order_total']) ?> ฿</span>
          <?php endif; ?>
          <?php if($promo['max_discount']!==null): ?>
            <span class="badge-pillx">Max Disc: <?= baht($promo['max_discount']) ?> ฿</span>
          <?php endif; ?>
        </div>
        <div class="small-note">ช่วงเวลา: <strong class="text-light"><?= h($promo['start_at']) ?></strong> → <strong class="text-light"><?= h($promo['end_at']) ?></strong></div>
        <div class="mt-2">
          สถานะ:
          <?php if($promo['is_active']): ?>
            <span class="badge-pillx">✅ กำลังใช้งาน</span>
          <?php else: ?>
            <span class="badge-pillx badge-pill-danger">❌ ปิดอยู่</span>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <form method="post" class="m-0">
          <input type="hidden" name="action" value="toggle_active">
          <button class="btn btn-toggle <?= $promo['is_active']?'btn-danger':'btn-success' ?>">
            <?= $promo['is_active']?'ปิดการใช้งาน':'เปิดการใช้งาน' ?>
          </button>
        </form>
      </div>
    </div>

    <?php if($msg): ?>
      <div class="alert alert-<?= $cls ?> mt-3 mb-0"><?= h($msg) ?></div>
    <?php endif; ?>
  </div>

  <!-- เมนูในโปรนี้ -->
  <div class="cardx mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="h5 m-0 h-title">เมนูที่อยู่ในโปรโมชันนี้</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:80px">#</th>
            <th>ชื่อเมนู</th>
            <th style="width:140px" class="text-right">ราคา (ปกติ)</th>
            <th style="width:160px">หมวดหมู่</th>
            <th style="width:120px" class="text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($menus_in)): ?>
            <tr><td colspan="5" class="text-center small-note">ยังไม่มีเมนูในโปรนี้</td></tr>
          <?php else: foreach($menus_in as $m): ?>
            <tr>
              <td><?= (int)$m['menu_id'] ?></td>
              <td><?= h($m['name']) ?></td>
              <td class="text-right"><?= baht($m['price']) ?></td>
              <td><?= h($m['category_name'] ?? '-') ?></td>
              <td class="text-right">
                <form method="post" class="d-inline" onsubmit="return confirm('นำเมนูนี้ออกจากโปร?');">
                  <input type="hidden" name="action" value="remove_menu">
                  <input type="hidden" name="menu_id" value="<?= (int)$m['menu_id'] ?>">
                  <button class="btn btn-sm btn-outline-light" style="border-color:#ff9f9c;color:#ff9f9c">นำออก</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- เพิ่มเมนูเข้ากับโปรนี้ -->
  <div class="cardx">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="h5 m-0 h-title">เพิ่มเมนูเข้ากับโปรโมชันนี้</div>
      <form class="form-inline" method="get" action="promo_detail.php">
        <input type="hidden" name="id" value="<?= (int)$promo_id ?>">
        <input name="q" class="form-control form-control-sm searchbox mr-2"
               value="<?= h($q) ?>"
               type="search" placeholder="ค้นหาเมนูที่จะเพิ่ม">
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>

    <form method="post" class="form-inline">
      <input type="hidden" name="action" value="add_menu">
      <select name="menu_id" class="form-control mr-2" required>
        <?php if ($menus_not_in && $menus_not_in->num_rows > 0): ?>
          <?php while($mi = $menus_not_in->fetch_assoc()): ?>
            <option value="<?= (int)$mi['menu_id'] ?>"><?= h($mi['name']) ?></option>
          <?php endwhile; ?>
        <?php else: ?>
          <option value="">— ไม่มีเมนูให้เพิ่ม —</option>
        <?php endif; ?>
      </select>
      <button class="btn btn-primary">+ เพิ่มเมนู</button>
    </form>
  </div>

</div>
</body>
</html>

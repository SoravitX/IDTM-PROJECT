<?php
// admin/promo_detail.php — ดู/แก้ไขโปรโมชัน + ผูก/ถอดเมนู + เปิด/ปิดโปร
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
  // มีค้นหา
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
  // ไม่มีค้นหา
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
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2; --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8; --ink:#0b2746;
}
body{margin:0; background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff; font-family:"Segoe UI",Tahoma,Arial}
.wrap{max-width:1100px; margin:20px auto; padding:0 14px}
.cardx{background:rgba(255,255,255,.95); color:var(--ink); border:1px solid #d9e6ff; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.22); padding:16px}
.topbar{position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18); margin-bottom:12px}
.badge-pillx{ display:inline-block; padding:.3rem .7rem; border-radius:999px; background:#eaf4ff; border:1px solid #cfe2ff; color:#0D4071; font-weight:800 }
.badge-pill-danger{ background:#ffe8e8; border-color:#ffc9cf; color:#7a1a1a }
.table thead th{ background:#f2f7ff; color:#083b6a; border-bottom:2px solid #e1ecff; font-weight:800 }
.table td,.table th{ border-color:#e9f2ff !important; vertical-align: middle !important; }
.searchbox{background:#fff; border:2px solid var(--psu-ocean-blue); color:#000; border-radius:999px; padding:.4rem .9rem; min-width:260px}
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}
.btn-toggle{ font-weight:800 }
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar d-flex align-items-center justify-content-between">
    <div>
      <div class="h5 m-0 font-weight-bold">รายละเอียดโปรโมชัน • PSU Blue Cafe</div>
      <small class="text-light">แก้ไขโปร, ผูกเมนู, เปิด/ปิดการใช้งาน</small>
    </div>
    <div>
      <a href="promo_list.php" class="btn btn-light btn-sm mr-2">← กลับรายการโปรโมชัน</a>
      <a href="adminmenu.php" class="btn btn-light btn-sm mr-2">ไปหน้า Admin</a>
      <a href="../front_store/front_store.php" class="btn btn-sm btn-ghost">ไปหน้าร้าน</a>
    </div>
  </div>

  <div class="cardx mb-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="h4 mb-1"><?= h($promo['name']) ?></div>
        <div class="mb-2">
          <span class="badge-pillx">Scope: <?= h($promo['scope']) ?></span>
          <span class="badge-pillx">Type: <?= h($promo['discount_type']) ?></span>
          <span class="badge-pillx">Value: <?= h($promo['discount_value']) ?></span>
          <?php if($promo['min_order_total']!==null): ?>
            <span class="badge-pillx">Min: <?= baht($promo['min_order_total']) ?> ฿</span>
          <?php endif; ?>
          <?php if($promo['max_discount']!==null): ?>
            <span class="badge-pillx">Max Disc: <?= baht($promo['max_discount']) ?> ฿</span>
          <?php endif; ?>
        </div>
        <div class="text-muted">ช่วงเวลา: <strong><?= h($promo['start_at']) ?></strong> → <strong><?= h($promo['end_at']) ?></strong></div>
        <div class="mt-1">
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
          <button class="btn btn-toggle btn-<?= $promo['is_active']?'danger':'success' ?>">
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
      <div class="h5 m-0">เมนูที่อยู่ในโปรโมชันนี้</div>
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
            <tr><td colspan="5" class="text-center text-muted">ยังไม่มีเมนูในโปรนี้</td></tr>
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
                  <button class="btn btn-sm btn-outline-danger">นำออก</button>
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
      <div class="h5 m-0">เพิ่มเมนูเข้ากับโปรโมชันนี้</div>
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

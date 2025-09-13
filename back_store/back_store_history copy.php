<?php
// back_store/back_store_history.php — ประวัติออเดอร์ที่อัปเดตแล้ว (ready/canceled) + PSU Topbar
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// จำกัดสิทธิ์ (ถ้ามี role ใน session)
$allow_roles = ['admin','employee','kitchen','back','barista'];
if (!empty($_SESSION['role']) && !in_array($_SESSION['role'], $allow_roles, true)) {
  header("Location: ../index.php"); exit;
}

function money_fmt($n){ return number_format((float)$n, 2); }

// รับตัวกรอง
$st   = $_GET['status'] ?? ''; // ready/canceled/''=all
$from = $_GET['from'] ?? '';   // YYYY-MM-DD
$to   = $_GET['to']   ?? '';   // YYYY-MM-DD
$qnum = trim($_GET['oid'] ?? ''); // ค้นหาเลขออเดอร์

// ✨ เพิ่มนับสลิปด้วย LEFT JOIN ซับเควรี (ps.cnt = จำนวนสลิป)
$sql = "
  SELECT o.order_id, o.user_id, o.order_time, o.updated_at, o.status, o.total_price,
         u.username, u.name,
         COALESCE(ps.cnt, 0) AS slip_count
  FROM orders o
  LEFT JOIN users u ON u.user_id = o.user_id
  LEFT JOIN (
    SELECT order_id, COUNT(*) AS cnt
    FROM payment_slips
    GROUP BY order_id
  ) ps ON ps.order_id = o.order_id
  WHERE o.status IN ('ready','canceled')
";

$params = []; $types='';
if ($st==='ready' || $st==='canceled'){ $sql.=" AND o.status=?"; $types.='s'; $params[]=$st; }
if ($from!==''){ $sql.=" AND DATE(o.order_time) >= ?"; $types.='s'; $params[]=$from; }
if ($to!==''){   $sql.=" AND DATE(o.order_time) <= ?"; $types.='s'; $params[]=$to; }
if ($qnum!==''){ $sql.=" AND o.order_id = ?"; $types.='i'; $params[]=(int)$qnum; }

$sql .= " ORDER BY o.order_id DESC LIMIT 300";

if ($types!==''){
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute(); $rs = $stmt->get_result(); $stmt->close();
} else {
  $rs = $conn->query($sql);
}
$orders = [];
while ($row = $rs->fetch_assoc()) $orders[] = $row;
$rs->close();

// รายการย่อย
function get_order_lines(mysqli $conn, int $oid): array {
  $rows = [];
  $stmt = $conn->prepare("
    SELECT d.order_detail_id, d.menu_id, d.quantity, d.note, d.total_price, m.name AS menu_name
    FROM order_details d JOIN menu m ON m.menu_id = d.menu_id
    WHERE d.order_id=? ORDER BY d.order_detail_id
  ");
  $stmt->bind_param("i", $oid);
  $stmt->execute(); $res = $stmt->get_result();
  while ($r=$res->fetch_assoc()) $rows[]=$r;
  $stmt->close();
  return $rows;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ประวัติออเดอร์ (Ready/Cancel)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-sritrang:#BBB4D8;
}
body{background:linear-gradient(135deg,var(--psu-deep-blue),var(--psu-ocean-blue));color:#fff;font-family:"Segoe UI",Tahoma;}
.wrap{max-width:1400px;margin:18px auto;padding:0 16px;}
/* Topbar */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin:16px auto 12px;
  border-radius:14px; background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18);
  max-width:1400px;
}
.topbar-actions{ gap:8px }
.badge-user{ background:#4173BD; color:#fff; font-weight:800; border-radius:999px }
@media (max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} .topbar-actions{width:100%; justify-content:flex-end} }

.card{background:#fff;color:#0D4071;border:1px solid #e7e9f2;border-radius:16px;box-shadow:0 10px 24px rgba(0,0,0,.14);overflow:hidden}
.head{display:flex;justify-content:space-between;align-items:flex-start;background:#f7fbff;border-bottom:1px solid #eef0f6;padding:12px 16px}
.badge-ready{background:#2e7d32} .badge-cancel{background:#d9534f}
.item{font-weight:800}
.note{margin-top:4px;font-size:.9rem;background:#f2f7ff;color:#123c6b;border:1px dashed #cfe0ff;border-radius:10px;padding:6px 8px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(520px,1fr));gap:16px}

/* ✨ วิธีชำระ (chips) */
.pay-chip{
  display:inline-flex; align-items:center; gap:6px;
  border-radius:999px; font-weight:800; padding:4px 10px;
  border:1px solid #d9e6ff; background:#eef5ff; color:#0D4071; margin-top:6px;
}
.pay-chip.cash{ background:#fff8e6; border-color:#ffe1a6; color:#7a4b00; }
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar d-flex align-items-center justify-content-between">
  <div class="d-flex align-items-center">
    <h4 class="m-0">PSU Blue Cafe • ประวัติออเดอร์</h4>
  </div>
  <div class="d-flex align-items-center topbar-actions">
    <a href="back_store.php" class="btn btn-primary btn-sm mr-2">กลับหน้าหลังร้าน</a>
    <a href="../SelectRole/role.php" class="btn btn-primary btn-sm mr-2">ตําเเหน่ง</a>
    <span class="badge badge-user px-3 py-2 mr-2">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
  </div>
</div>

<div class="wrap">
  <form class="card p-3 mb-3" method="get" style="color:#0D4071">
    <div class="form-row">
      <div class="col-md-2">
        <label>สถานะ</label>
        <select class="form-control" name="status">
          <option value="">ทั้งหมด</option>
          <option value="ready"   <?= $st==='ready'?'selected':'' ?>>ready</option>
          <option value="canceled"<?= $st==='canceled'?'selected':'' ?>>canceled</option>
        </select>
      </div>
      <div class="col-md-2">
        <label>จากวันที่</label>
        <input type="date" class="form-control" name="from" value="<?= htmlspecialchars($from,ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="col-md-2">
        <label>ถึงวันที่</label>
        <input type="date" class="form-control" name="to" value="<?= htmlspecialchars($to,ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="col-md-2">
        <label>เลขออเดอร์</label>
        <input type="number" class="form-control" name="oid" value="<?= htmlspecialchars($qnum,ENT_QUOTES,'UTF-8') ?>">
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-primary btn-block">ค้นหา</button>
      </div>
    </div>
  </form>

  <div class="grid">
    <?php if (empty($orders)): ?>
      <div class="card p-4">ไม่พบรายการ</div>
    <?php else: ?>
      <?php foreach ($orders as $o): $lines = get_order_lines($conn,(int)$o['order_id']); ?>
        <?php
          // ✨ ตัดสินใจวิธีชำระจากจำนวนสลิป
          $isTransfer = ((int)$o['slip_count'] > 0);
          $pay_text   = $isTransfer ? '💳 โอนเงิน' : '💵 เงินสด';
          $pay_class  = $isTransfer ? '' : ' cash';
        ?>
        <div class="card">
          <div class="head">
            <div>
              <div class="h5 font-weight-bold mb-1">#<?= (int)$o['order_id'] ?> — <?= htmlspecialchars($o['username'] ?? 'user',ENT_QUOTES,'UTF-8') ?></div>
              <div><small>สั่ง: <?= htmlspecialchars($o['order_time'],ENT_QUOTES,'UTF-8') ?></small></div>
              <?php if(!empty($o['updated_at'])): ?>
                <div><small>อัปเดต: <?= htmlspecialchars($o['updated_at'],ENT_QUOTES,'UTF-8') ?></small></div>
              <?php endif; ?>
              <div class="pay-chip<?= $pay_class ?>"><?= $pay_text ?></div>
            </div>
            <span class="badge <?= $o['status']==='ready'?'badge-ready':'badge-cancel' ?> p-2"><?= htmlspecialchars($o['status'],ENT_QUOTES,'UTF-8') ?></span>
          </div>

          <div class="p-3" style="color:#0D4071">
            <?php foreach ($lines as $ln): ?>
              <div class="d-flex justify-content-between border-bottom py-2" style="border-color:#eef0f6">
                <div style="max-width:78%;padding-right:12px">
                  <div class="item"><?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?></div>
                  <?php if(!empty($ln['note'])): ?>
                    <div class="note">📝 <?= htmlspecialchars($ln['note'],ENT_QUOTES,'UTF-8') ?></div>
                  <?php endif; ?>
                </div>
                <div><span class="badge badge-secondary p-2">x <?= (int)$ln['quantity'] ?></span></div>
              </div>
            <?php endforeach; ?>
            <div class="d-flex justify-content-between font-weight-bold mt-2">
              <div>ยอดรวม</div>
              <div><?= money_fmt($o['total_price']) ?> ฿</div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

<?php
// SelectRole/check_order.php — แสดงออเดอร์ทั้งหมด (มีตัวกรอง เมนู + วันเวลา + สถานะ)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_fmt($n){ return number_format((float)$n, 2); }

/* --------- รับค่าตัวกรอง --------- */
$status    = $_GET['status']     ?? 'all';              // all | pending | ready | canceled
$q         = trim((string)($_GET['q'] ?? ''));          // ชื่อเมนู
$date_from = trim((string)($_GET['date_from'] ?? ''));
$time_from = trim((string)($_GET['time_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));
$time_to   = trim((string)($_GET['time_to'] ?? ''));

$dt_from = $date_from ? ($date_from.' '.($time_from ?: '00:00:00')) : '';
$dt_to   = $date_to   ? ($date_to  .' '.($time_to   ?: '23:59:59')) : '';

/* --------- สร้างเงื่อนไข/ดึงหัวออเดอร์ --------- */
$where  = '1=1';
$types  = '';
$params = [];

if ($status !== 'all') {
  $where  .= ' AND o.status = ?';
  $types  .= 's';
  $params []= $status;
}
if ($dt_from !== '') {
  $where  .= ' AND o.order_time >= ?';
  $types  .= 's';
  $params []= $dt_from;
}
if ($dt_to !== '') {
  $where  .= ' AND o.order_time <= ?';
  $types  .= 's';
  $params []= $dt_to;
}
if ($q !== '') {
  // มีเมนูชื่อคล้าย q อยู่ในออเดอร์นี้หรือไม่
  $where  .= " AND EXISTS(
                 SELECT 1 FROM order_details d
                 JOIN menu m ON m.menu_id=d.menu_id
                 WHERE d.order_id=o.order_id AND m.name LIKE ?
               )";
  $types  .= 's';
  $params []= '%'.$q.'%';
}

$sql = "
  SELECT o.order_id, o.user_id, o.order_time, o.status, o.total_price,
         u.username, u.name
  FROM orders o
  JOIN users u ON u.user_id=o.user_id
  WHERE $where
  ORDER BY o.order_time DESC
";
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$orders_rs = $stmt->get_result();

$orders = [];
$order_ids = [];
while ($row = $orders_rs->fetch_assoc()) {
  $orders[] = $row;
  $order_ids[] = (int)$row['order_id'];
}
$stmt->close();

/* --------- ดึงรายละเอียดเฉพาะออเดอร์ที่แสดง --------- */
$details = [];
if (!empty($order_ids)) {
  // สร้าง placeholders สำหรับ IN(...)
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types_in = str_repeat('i', count($order_ids));

  $sql2 = "
    SELECT d.order_id, d.menu_id, d.quantity, d.note, d.total_price,
           m.name AS menu_name, m.price AS unit_price
    FROM order_details d
    JOIN menu m ON m.menu_id = d.menu_id
    WHERE d.order_id IN ($in)
    ORDER BY d.order_detail_id
  ";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param($types_in, ...$order_ids);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  while ($r = $res2->fetch_assoc()) {
    $details[$r['order_id']][] = $r;
  }
  $stmt2->close();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เช็คออเดอร์ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<style>
:root {
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman-blue:#0094B3;
  --psu-sky-blue:#29ABE2; --psu-river-blue:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ok:#2e7d32; --warn:#f0ad4e; --bad:#d9534f; --shadow:0 8px 20px rgba(0,0,0,.1);
}
body{
  background:linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue));
  color:#fff; font-family:"Segoe UI", Tahoma, sans-serif; min-height:100vh;
}
.wrap{max-width:1280px; margin:28px auto; padding:0 16px;}
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin-bottom:12px;
  border-radius:14px; background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.brand{font-weight:900; letter-spacing:.3px; color:#fff; margin:0}
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }
.topbar-actions{ gap:8px }
.topbar .btn-primary{ background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800 }

.filter{
  background:rgba(255,255,255,.10); border:1px solid var(--psu-sritrang);
  border-radius:14px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,.18); margin-bottom:16px;
}
.filter label{font-weight:700; font-size:.9rem}
.filter .form-control, .filter .custom-select{ border-radius:999px; border:1px solid #d8e6ff }
.filter .btn-find{font-weight:800; border-radius:999px}
.filter .btn-clear{border-radius:999px}

.grid{
  display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap:20px;
}
.card-order{
  background:#fff; color:var(--psu-deep-blue);
  border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:var(--shadow);
  display:flex; flex-direction:column; overflow:hidden; transition:.2s;
}
.card-order:hover{ transform:translateY(-3px); box-shadow:0 12px 28px rgba(0,0,0,.18); }
.co-head{
  padding:12px 16px; border-bottom:1px solid var(--psu-sritrang);
  background:var(--psu-sky-blue); color:#fff; display:flex; justify-content:space-between; align-items:center;
}
.oid{font-weight:700; font-size:1.1rem}
.meta{font-size:.85rem}
.badge-status{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:600; background:#fff }
.st-pending{color:var(--warn)} .st-ready{color:var(--ok)} .st-canceled{color:var(--bad)}
.dot{width:8px; height:8px; border-radius:50%; background:currentColor}
.co-body{padding:14px 16px; flex:1}
.line{display:flex; justify-content:space-between; margin-bottom:8px; font-size:.95rem}
.note{ margin-top:2px; font-size:.8rem; color:var(--psu-deep-blue); background:var(--psu-sritrang); border-radius:6px; padding:2px 6px; display:inline-block; }
.money{font-weight:700; color:var(--psu-ocean-blue)}
.divider{border-top:1px dashed var(--psu-sritrang); margin:8px 0}
.co-foot{ background:var(--psu-ocean-blue); color:#fff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 16px 16px }
.sum-l{font-weight:600} .sum-r{font-size:1.1rem; font-weight:900}
@media (max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} .topbar-actions{width:100%; justify-content:flex-end} }
</style>
</head>
<body>
<div class="wrap">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between">
    <h4 class="brand">PSU Blue Cafe • เช็คออเดอร์</h4>
    <div class="d-flex align-items-center topbar-actions">
      <a href="../front_store/front_store.php" class="btn btn-primary btn-sm">หน้าร้าน</a>
      <a href="../SelectRole/role.php" class="btn btn-primary btn-sm">ตําเเหน่ง</a>
     
      <span class="badge badge-user px-3 py-2">
        ผู้ใช้: <?= h($_SESSION['username'] ?? '') ?>
      </span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <!-- Filter bar -->
  <form class="filter" method="get">
    <div class="form-row">
      <div class="col-md-2 mb-2">
        <label>สถานะ</label>
        <select name="status" class="custom-select">
          <?php
            $opts = ['all'=>'(ทั้งหมด)','pending'=>'Pending','ready'=>'Ready','canceled'=>'Canceled'];
            foreach($opts as $k=>$v){
              $sel = ($status===$k)?'selected':'';
              echo '<option value="'.h($k).'" '.$sel.'>'.h($v).'</option>';
            }
          ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label>ค้นหาชื่อเมนู</label>
        <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="เช่น ชาไทย">
      </div>
      <div class="col-md-3 mb-2">
        <label>ตั้งแต่ (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>"></div>
          <div class="col"><input type="time" name="time_from" class="form-control" value="<?= h($time_from) ?>"></div>
        </div>
      </div>
      <div class="col-md-3 mb-2">
        <label>ถึง (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>"></div>
          <div class="col"><input type="time" name="time_to" class="form-control" value="<?= h($time_to) ?>"></div>
        </div>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <button class="btn btn-primary btn-block btn-find">ค้นหา</button>
      </div>
    </div>
    <div class="text-light small mt-1">เคล็ดลับ: กำหนดเฉพาะวันที่ก็ได้ ระบบจะเติมเวลาให้เป็น 00:00 ถึง 23:59 อัตโนมัติ</div>
  </form>

  <?php if(!empty($orders)): ?>
    <div class="grid">
      <?php foreach($orders as $o):
        $statusClass = ($o['status']==='ready'?'st-ready':($o['status']==='canceled'?'st-canceled':'st-pending'));
        $rows = $details[$o['order_id']] ?? [];
      ?>
      <div class="card-order">
        <div class="co-head">
          <div>
            <div class="oid">#<?= (int)$o['order_id'] ?></div>
            <div class="meta"><?= h($o['order_time']) ?></div>
          </div>
          <div class="badge-status <?= $statusClass ?>">
            <span class="dot"></span>
            <?= h($o['status']) ?>
          </div>
        </div>

        <div class="co-body">
          <?php if(!empty($rows)): foreach($rows as $r): ?>
            <div class="line">
              <div class="left">
                <div class="qtyname">
                  <?= (int)$r['quantity'] ?> × <?= h($r['menu_name']) ?>
                </div>
                <?php if(!empty($r['note'])): ?>
                  <div class="note"><?= h($r['note']) ?></div>
                <?php endif; ?>
              </div>
              <div class="money"><?= money_fmt($r['total_price']) ?> ฿</div>
            </div>
          <?php endforeach; else: ?>
            <div class="text-muted">ไม่มีรายการอาหาร</div>
          <?php endif; ?>
          <div class="divider"></div>
        </div>

        <div class="co-foot">
          <div class="sum-l">รวมทั้งออเดอร์</div>
          <div class="sum-r"><?= money_fmt($o['total_price']) ?> ฿</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center">ไม่พบออเดอร์ตามเงื่อนไข</div>
  <?php endif; ?>

</div>
</body>
</html>

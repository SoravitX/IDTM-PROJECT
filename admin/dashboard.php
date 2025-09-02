<?php
// dashboard.php — รายงานสรุป & เมนูขายดี (Today / Week / Month / Custom)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// ===== Helpers =====
function money_fmt($n){ return number_format((float)$n, 2); }
function dt_ymd($s){ return (new DateTime($s))->format('Y-m-d'); }

// กำหนดสถานะออเดอร์ที่นับเป็น “ขายจริง”
$OK_STATUSES = ["ready","completed","paid","served"]; // 'pending' ยังทำอยู่, 'canceled' ไม่คิด

// รับ period: today | week | month | custom
$period = $_GET['period'] ?? 'today';
$start  = $_GET['start']  ?? '';
$end    = $_GET['end']    ?? '';

$now = new DateTime('now');
$tzTodayStart = (clone $now)->setTime(0,0,0);

if ($period === 'today') {
  $range_start = $tzTodayStart;
  $range_end   = (clone $tzTodayStart)->modify('+1 day');
} elseif ($period === 'week') {
  $range_start = (clone $tzTodayStart)->modify('monday this week');
  $range_end   = (clone $range_start)->modify('+7 days');
} elseif ($period === 'month') {
  $range_start = (clone $tzTodayStart)->modify('first day of this month');
  $range_end   = (clone $range_start)->modify('first day of next month');
} else { // custom
  $range_start = $start ? new DateTime($start.' 00:00:00') : (clone $tzTodayStart);
  $range_end   = $end   ? (new DateTime($end.' 23:59:59')) : (clone $tzTodayStart)->modify('+1 day');
  $period = 'custom';
}

$rangeStartStr = $range_start->format('Y-m-d H:i:s');
$rangeEndStr   = $range_end->format('Y-m-d H:i:s');

// ===== Queries =====

// 1) KPI: จำนวนออเดอร์, รายได้รวม
$placeholders = implode(',', array_fill(0, count($OK_STATUSES), '?'));
$sqlKpi = "
  SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_price),0) AS revenue
  FROM orders
  WHERE order_time >= ? AND order_time < ?
    AND status IN ($placeholders)";
$stmt = $conn->prepare($sqlKpi);
$types = 'ss' . str_repeat('s', count($OK_STATUSES));
$stmt->bind_param($types, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc() ?: ['orders_count'=>0, 'revenue'=>0.0];
$stmt->close();

// จำนวนแก้ว
$sqlCups = "
  SELECT COALESCE(SUM(od.quantity),0) AS cups
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($placeholders)";
$stmt = $conn->prepare($sqlCups);
$stmt->bind_param($types, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$rowCups = $stmt->get_result()->fetch_assoc();
$stmt->close();
$kpi['cups'] = (int)($rowCups['cups'] ?? 0);

// 2) เมนูขายดี Top N
$TOP_N = 8;
$sqlTop = "
  SELECT m.menu_id, m.name,
         COALESCE(SUM(od.quantity),0) AS qty,
         COALESCE(SUM(od.total_price),0) AS sales
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  JOIN menu   m ON m.menu_id   = od.menu_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($placeholders)
  GROUP BY m.menu_id, m.name
  ORDER BY qty DESC, sales DESC
  LIMIT {$TOP_N}";
$stmt = $conn->prepare($sqlTop);
$stmt->bind_param($types, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$topItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// เอาไว้ทำกราฟ (Top 5)
$chartItems = array_slice($topItems, 0, 5);

// 3) ออเดอร์ตามสถานะ (pie)
$sqlByStatus = "
  SELECT o.status, COUNT(*) AS c
  FROM orders o
  WHERE o.order_time >= ? AND o.order_time < ?
  GROUP BY o.status";
$stmt = $conn->prepare($sqlByStatus);
$stmt->bind_param('ss', $rangeStartStr, $rangeEndStr);
$stmt->execute();
$byStatus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ช่วงวันที่แสดงบนหัวข้อ
$displayRange = dt_ymd($range_start->format('Y-m-d'))." → ".dt_ymd($range_end->modify('-1 second')->format('Y-m-d'));
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>PSU Blue Cafe • Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
:root{
  --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-sky:#29ABE2; --psu-sritrang:#BBB4D8;
  --psu-andaman:#0094B3; --psu-river:#4EC5E0;
}

/* พื้นหลัง & ฟอนต์ */
body{
  background: linear-gradient(135deg,#0D4071,#4173BD);
  color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{ max-width:1400px; margin:18px auto; padding:0 12px; }

/* ===== Navbar แบบเดียวกับหน้าอื่น ===== */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin-bottom:14px;
  border-radius:14px;
  background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.brand{font-weight:900; letter-spacing:.3px; color:#fff; margin:0}
.topbar-actions{ gap:8px; }
.badge-user{
  background:var(--psu-ocean); color:#fff; font-weight:800; border-radius:999px;
}
.topbar .btn-primary{
  background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800;
}
.btn-ghost{
  background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700;
}
@media (max-width:576px){
  .topbar{flex-wrap:wrap; gap:8px}
  .topbar-actions{width:100%; justify-content:flex-end}
}

/* ===== การ์ด/ตาราง ===== */
.cardx{
  background:rgba(255,255,255,.92); color:#0b2746; border:1px solid #d9e6ff;
  border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.kpi{ display:grid; grid-template-columns: repeat(4, minmax(180px,1fr)); gap:12px; }
.kpi .tile{ padding:14px 16px; border-radius:14px; background:#f7fbff; border:1px solid #e3efff; }
.kpi .n{ font-size:1.6rem; font-weight:900; color:#0D4071 }
.kpi .l{ font-weight:800; color:#1b4b83; opacity:.9 }
.controls .btn{ font-weight:800 }
.badge-lightx{
  background:#eaf4ff; color:#0b3c6a; border:1px solid #cfe2ff; border-radius:999px; padding:.2rem .6rem; font-weight:800
}
.table thead th{ background:#f2f7ff; color:#083b6a; border-bottom:2px solid #e1ecff; }
.table td, .table th{ border-color:#e9f2ff !important; }

a.btn-back{ color:#fff; }
</style>
</head>
<body>
<div class="wrap">

  <!-- ===== Navbar (เหมือนหน้าอื่น) ===== -->
  <div class="topbar d-flex align-items-center justify-content-between">
    <h4 class="brand mb-0">PSU Blue Cafe • Dashboard</h4>
    <div class="d-flex align-items-center topbar-actions">
     
      <a href="adminmenu.php" class="btn btn-light btn-sm">ไปหน้า Admin</a>
      <a href="attendance_admin.php" class="btn btn-light btn-sm">เวลาทำงาน</a>
      <span class="badge badge-user px-3 py-2">
        ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? ($_SESSION['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
      </span>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <!-- Header ช่วงวันที่ -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h3 class="mb-0 mr-3" style="font-weight:900;">ภาพรวม</h3>
      <span class="badge badge-lightx">ช่วงที่แสดง: <?= htmlspecialchars($displayRange,ENT_QUOTES,'UTF-8') ?></span>
    </div>
  </div>

  <!-- Filter -->
  <div class="cardx mb-3">
    <div class="p-3 controls d-flex align-items-center flex-wrap">
      <form class="form-inline m-0" method="get">
        <div class="btn-group mr-2 mb-2">
          <a href="dashboard.php?period=today" class="btn btn-sm <?= $period==='today'?'btn-primary':'btn-outline-primary' ?>">วันนี้</a>
          <a href="dashboard.php?period=week"  class="btn btn-sm <?= $period==='week'?'btn-primary':'btn-outline-primary' ?>">สัปดาห์นี้</a>
          <a href="dashboard.php?period=month" class="btn btn-sm <?= $period==='month'?'btn-primary':'btn-outline-primary' ?>">เดือนนี้</a>
        </div>
        <input type="hidden" name="period" value="custom">
        <label class="mr-2 mb-2">จาก</label>
        <input type="date" class="form-control form-control-sm mr-2 mb-2" name="start" value="<?= htmlspecialchars($start?:$range_start->format('Y-m-d'),ENT_QUOTES,'UTF-8') ?>">
        <label class="mr-2 mb-2">ถึง</label>
        <input type="date" class="form-control form-control-sm mr-2 mb-2" name="end" value="<?= htmlspecialchars($end?:$range_end->format('Y-m-d'),ENT_QUOTES,'UTF-8') ?>">
        <button class="btn btn-sm btn-success mb-2">แสดง</button>
      </form>
    </div>
  </div>

  <!-- KPI Tiles -->
  <div class="cardx mb-3 p-3">
    <div class="kpi">
      <div class="tile">
        <div class="l">จำนวนออเดอร์</div>
        <div class="n"><?= (int)$kpi['orders_count'] ?></div>
      </div>
      <div class="tile">
        <div class="l">จำนวนแก้ว (ชิ้น)</div>
        <div class="n"><?= (int)$kpi['cups'] ?></div>
      </div>
      <div class="tile">
        <div class="l">รายได้รวม</div>
        <div class="n"><?= money_fmt($kpi['revenue']) ?> ฿</div>
      </div>
      <div class="tile">
        <div class="l">มูลค่าเฉลี่ย/ออเดอร์</div>
        <div class="n">
          <?= (int)$kpi['orders_count']>0 ? money_fmt(((float)$kpi['revenue'])/(int)$kpi['orders_count']) : '0.00' ?> ฿
        </div>
      </div>
    </div>
  </div>

  <!-- Top items + charts -->
  <div class="row">
    <div class="col-lg-7 mb-3">
      <div class="cardx p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-2" style="font-weight:900;color:#0D4071">เมนูขายดี (Top <?= $TOP_N ?>)</h5>
          <span class="badge badge-lightx">ไม่รวมออเดอร์ยกเลิก</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th style="width:60%">เมนู</th>
                <th class="text-right">จำนวน</th>
                <th class="text-right">ยอดขาย (฿)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$topItems): ?>
                <tr><td colspan="3" class="text-center text-muted">ไม่มีข้อมูลในช่วงที่เลือก</td></tr>
              <?php else: foreach ($topItems as $i): ?>
                <tr>
                  <td><?= htmlspecialchars($i['name'],ENT_QUOTES,'UTF-8') ?></td>
                  <td class="text-right"><?= (int)$i['qty'] ?></td>
                  <td class="text-right"><?= money_fmt($i['sales']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-5 mb-3">
      <div class="cardx p-3 h-100">
        <h5 class="mb-2" style="font-weight:900;color:#0D4071">กราฟภาพรวม</h5>
        <canvas id="chartTop" height="180"></canvas>
        <hr>
        <canvas id="chartStatus" height="160"></canvas>
      </div>
    </div>
  </div>
</div>

<script>
// Top 5 bar
const topLabels = <?= json_encode(array_column($chartItems,'name'), JSON_UNESCAPED_UNICODE) ?>;
const topQty    = <?= json_encode(array_map('intval', array_column($chartItems,'qty'))) ?>;

new Chart(document.getElementById('chartTop'), {
  type: 'bar',
  data: { labels: topLabels, datasets: [{ label: 'จำนวน (แก้ว)', data: topQty }]},
  options: { plugins:{ legend:{ display:false }}, scales:{ y:{ beginAtZero:true } } }
});

// Status pie
const stLabels = <?= json_encode(array_column($byStatus,'status'), JSON_UNESCAPED_UNICODE) ?>;
const stData   = <?= json_encode(array_map('intval', array_column($byStatus,'c'))) ?>;

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: { labels: stLabels, datasets: [{ data: stData }]},
  options: { plugins:{ legend:{ position:'bottom' } } }
});
</script>
</body>
</html>

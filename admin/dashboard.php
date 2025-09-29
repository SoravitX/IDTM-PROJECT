<?php
// dashboard.php — รายงานสรุป & เมนูขายดี (KPI: รายได้ท็อปปิง + ส่วนลดโปร) — FRONT_STORE TONE
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ===== Helpers ===== */
function money_fmt($n){ return number_format((float)$n, 2); }
function dt_ymd($s){ return (new DateTime($s))->format('Y-m-d'); }

/* ===== สถานะออเดอร์ที่นับเป็นยอดขายจริง ===== */
$OK_STATUSES = ["ready","completed","paid","served"];

/* ===== รับช่วงเวลา ===== */
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

/* ===== Placeholders ===== */
$placeholders = implode(',', array_fill(0, count($OK_STATUSES), '?'));
$typesCommon  = 'ss' . str_repeat('s', count($OK_STATUSES));

/* ===== 1) KPI หลัก ===== */
$sqlKpi = "
  SELECT COUNT(*) AS orders_count, COALESCE(SUM(total_price),0) AS revenue
  FROM orders
  WHERE order_time >= ? AND order_time < ?
    AND status IN ($placeholders)
";
$stmt = $conn->prepare($sqlKpi);
$stmt->bind_param($typesCommon, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$kpi = $stmt->get_result()->fetch_assoc() ?: ['orders_count'=>0, 'revenue'=>0.0];
$stmt->close();

/* ===== 1.1) จำนวนแก้ว ===== */
$sqlCups = "
  SELECT COALESCE(SUM(od.quantity),0) AS cups
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($placeholders)
";
$stmt = $conn->prepare($sqlCups);
$stmt->bind_param($typesCommon, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$rowCups = $stmt->get_result()->fetch_assoc();
$stmt->close();
$kpi['cups'] = (int)($rowCups['cups'] ?? 0);

/* ===== 1.2) KPI เพิ่ม: มูลค่าท็อปปิง & ส่วนลดโปร ===== */
$sqlExtra = "
  SELECT
    COALESCE(SUM(
      GREATEST(
        od.total_price - (
          (m.price - COALESCE(
            CASE
              WHEN p.promo_id IS NULL THEN 0
              WHEN p.discount_type='PERCENT'
                THEN LEAST((p.discount_value/100.0)*m.price, COALESCE(p.max_discount, 999999999))
              ELSE LEAST(p.discount_value, COALESCE(p.max_discount, 999999999))
            END, 0)
          ) * od.quantity
        )
      , 0)
    ), 0) AS topping_total,

    COALESCE(SUM(
      COALESCE(
        CASE
          WHEN p.promo_id IS NULL THEN 0
          WHEN p.discount_type='PERCENT'
            THEN LEAST((p.discount_value/100.0)*m.price, COALESCE(p.max_discount, 999999999))
          ELSE LEAST(p.discount_value, COALESCE(p.max_discount, 999999999))
        END, 0
      ) * od.quantity
    ), 0) AS discount_total
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  JOIN menu   m ON m.menu_id  = od.menu_id
  LEFT JOIN promotions p ON p.promo_id = od.promo_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($placeholders)
";
$stmt = $conn->prepare($sqlExtra);
$stmt->bind_param($typesCommon, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$extra = $stmt->get_result()->fetch_assoc() ?: ['topping_total'=>0.0,'discount_total'=>0.0];
$stmt->close();

/* ===== 2) เมนูขายดี ===== */
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
  LIMIT {$TOP_N}
";
$stmt = $conn->prepare($sqlTop);
$stmt->bind_param($typesCommon, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$topItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$chartItems = array_slice($topItems, 0, 5);

/* ===== 3) แบ่งตามสถานะ ===== */
$sqlByStatus = "
  SELECT o.status, COUNT(*) AS c
  FROM orders o
  WHERE o.order_time >= ? AND o.order_time < ?
  GROUP BY o.status
";
$stmt = $conn->prepare($sqlByStatus);
$stmt->bind_param('ss', $rangeStartStr, $rangeEndStr);
$stmt->execute();
$byStatus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ===== ช่วงวันที่แสดง ===== */
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
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* ========= FRONT_STORE TONE ========= */
:root{
  /* โทนหลัก */
  --bg1:#11161b; --bg2:#141b22;
  --surface:#1a2230; --surface-2:#192231; --surface-3:#202a3a;

  --text-strong:#ffffff;
  --text-normal:#e9eef6;
  --text-muted:#b9c6d6;

  --brand-500:#3aa3ff;
  --brand-400:#7cbcfd;
  --brand-300:#a9cffd;

  --ok:#22c55e; --danger:#e53935;

  --shadow: none; --shadow-lg:none;
}

body{
  background: linear-gradient(180deg,var(--bg1),var(--bg2));
  color: var(--text-normal);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{ max-width:1400px; margin:18px auto; padding:0 12px; }

/* Topbar */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin-bottom:14px;
  border-radius:10px;
  background: var(--surface);
  border:1px solid rgba(255,255,255,.08);
  box-shadow: none;
}
.brand{font-weight:900; letter-spacing:.3px; color:var(--text-strong); margin:0; display:flex; align-items:center; gap:8px}
.topbar-actions{ gap:8px; }
.badge-user{ background: var(--surface-3); color: var(--text-strong); font-weight:800; border-radius:999px; border:1px solid rgba(255,255,255,.12); }
.topbar .btn-primary,
.btn-primary.btn-export{
  background: var(--brand-500) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important; font-weight:800 !important; box-shadow:none !important;
}
.btn-outline-light{ color:#eaf2ff; border:1px solid rgba(255,255,255,.18); }

/* Card */
.cardx{
  background: var(--surface); color: var(--text-normal);
  border:1px solid rgba(255,255,255,.08);
  border-radius:10px; box-shadow:none;
}
.cardx .card-head{
  padding:10px 14px; border-bottom:1px solid rgba(255,255,255,.10);
  background: var(--surface-2); color: var(--text-strong);
}

/* KPI grid */
.kpi{ display:grid; grid-template-columns: repeat(6, minmax(180px,1fr)); gap:12px; }
.kpi .tile{
  padding:14px 16px; border-radius:10px;
  background: var(--surface-2);
  border:1px solid rgba(255,255,255,.10); position:relative; overflow:hidden;
}
.kpi .tile .ico{ position:absolute; right:10px; top:8px; font-size:1.4rem; opacity:.25; color:var(--brand-300); }
.kpi .n{ font-size:1.6rem; font-weight:900; color:#fff }
.kpi .l{ font-weight:800; color:#eaf2ff; display:flex; align-items:center; gap:8px }

/* Utils */
.badge-lightx{
  background:var(--surface-3); color:#eaf2ff;
  border:1px solid rgba(255,255,255,.12); border-radius:999px;
  padding:.25rem .6rem; font-weight:800
}

/* Filter controls */
.form-control, .custom-select{
  background: var(--surface-3);
  color: var(--text-normal);
  border:1px solid rgba(255,255,255,.12);
  border-radius:10px;
}
.form-control::placeholder{ color: rgba(255,255,255,.45); }
.form-control:focus, .custom-select:focus{
  box-shadow: none; border-color: rgba(122,184,255,.6);
}
.btn-success{
  background: var(--ok); border:1px solid #15803D; font-weight:800; color:#06260f;
}

/* Table */
.table thead th{
  background: var(--surface-2); color: var(--text-strong);
  border-bottom:1px solid rgba(255,255,255,.10);
  font-weight:800;
}
.table td, .table th{ border-color: rgba(255,255,255,.10) !important; }
.table tbody td, .table tbody th { color: var(--text-normal); }
.table tbody td:first-child { color: var(--text-strong); font-weight:800; }
.table tbody td.text-right { color: var(--brand-300); font-weight:800; }
.table tbody tr:hover td{ background: rgba(255,255,255,.03); color: var(--text-strong); }

/* Responsive */
@media (max-width: 1100px){ .kpi{ grid-template-columns: repeat(2, minmax(180px,1fr)); } }

/* Canvas */
canvas{ background: transparent; }
/* === Admin button: Front_Store blue === */
.btn-admin{
  background: linear-gradient(180deg, #56b2ff, #3aa3ff);
  border: 1px solid #1e6acc;
  color:#fff !important;
  font-weight:900;
  border-radius:12px;
  padding:.35rem .8rem;
  box-shadow:0 8px 22px rgba(58,163,255,.28);
}
.btn-admin .bi{ margin-right:.35rem; }
.btn-admin:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff !important; }
.btn-admin:focus{ outline:3px solid rgba(58,163,255,.35); outline-offset:2px; }

</style>
</head>
<body>
<div class="wrap">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between">
    <h4 class="brand mb-0"><i class="bi bi-speedometer2"></i> PSU Blue Cafe • Dashboard</h4>
    <div class="d-flex align-items-center topbar-actions">
<a href="adminmenu.php" class="btn btn-admin btn-sm">
  <i class="bi bi-gear"></i> เมนูเเอดมิน
</a>

     
      <a href="../logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center flex-wrap">
      <h3 class="mb-0 mr-3" style="font-weight:900;display:flex;align-items:center;gap:8px;color:#eaf2ff">
        <i class="bi bi-graph-up-arrow"></i> ภาพรวม
      </h3>
      <span class="badge badge-lightx"><i class="bi bi-calendar2-week"></i> ช่วงที่แสดง: <?= htmlspecialchars($displayRange,ENT_QUOTES,'UTF-8') ?></span>
    </div>
  </div>

  <!-- Filter -->
  <div class="cardx mb-3">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div class="font-weight-bold"><i class="bi bi-funnel"></i> ตัวกรองช่วงเวลา</div>
    </div>
    <div class="p-3 d-flex align-items-center flex-wrap">
      <form class="form-inline m-0" method="get" id="frmPeriod">
        <div class="btn-group mr-2 mb-2" role="group" aria-label="ช่วงเวลาเร็ว">
          <a href="dashboard.php?period=today" class="btn btn-sm <?= $period==='today'?'btn-primary':'btn-outline-light' ?>"><i class="bi bi-brightness-alt-high"></i> วันนี้</a>
          <a href="dashboard.php?period=week"  class="btn btn-sm <?= $period==='week'?'btn-primary':'btn-outline-light' ?>"><i class="bi bi-calendar-week"></i> สัปดาห์นี้</a>
          <a href="dashboard.php?period=month" class="btn btn-sm <?= $period==='month'?'btn-primary':'btn-outline-light' ?>"><i class="bi bi-calendar3"></i> เดือนนี้</a>
        </div>

        <input type="hidden" name="period" value="custom">
        <label class="mr-2 mb-2">จาก</label>
        <input type="date" class="form-control form-control-sm mr-2 mb-2" name="start" value="<?= htmlspecialchars($start?:$range_start->format('Y-m-d'),ENT_QUOTES,'UTF-8') ?>">
        <label class="mr-2 mb-2">ถึง</label>
        <input type="date" class="form-control form-control-sm mr-2 mb-2" name="end" value="<?= htmlspecialchars($end?:$range_end->format('Y-m-d'),ENT_QUOTES,'UTF-8') ?>">
        <button class="btn btn-sm btn-success mb-2"><i class="bi bi-arrow-repeat"></i> แสดง</button>

        <?php
          $expQs = http_build_query([
            'period' => $_GET['period'] ?? $period,
            'start'  => $_GET['start']  ?? $start,
            'end'    => $_GET['end']    ?? $end,
          ]);
        ?>
        <a href="export_excel.php?<?= $expQs ?>" class="btn btn-sm btn-primary ml-2 mb-2 btn-export">
          <i class="bi bi-file-earmark-spreadsheet"></i> Export Excel
        </a>
      </form>
    </div>
  </div>

  <!-- KPI Tiles -->
  <div class="cardx mb-3 p-3">
    <div class="kpi">
      <div class="tile" title="จำนวนออเดอร์ในช่วงที่เลือก">
        <div class="ico"><i class="bi bi-receipt"></i></div>
        <div class="l"><i class="bi bi-receipt-cutoff"></i> จำนวนออเดอร์</div>
        <div class="n"><?= (int)$kpi['orders_count'] ?></div>
      </div>
      <div class="tile" title="จำนวนชิ้น/แก้วที่ขาย">
        <div class="ico"><i class="bi bi-cup-hot"></i></div>
        <div class="l"><i class="bi bi-cup-straw"></i> จำนวนแก้ว (ชิ้น)</div>
        <div class="n"><?= (int)$kpi['cups'] ?></div>
      </div>
      <div class="tile" title="รายได้รวม (ตามสถานะที่นับเป็นยอดขายจริง)">
        <div class="ico"><i class="bi bi-cash-coin"></i></div>
        <div class="l"><i class="bi bi-currency-exchange"></i> รายได้รวม</div>
        <div class="n"><?= money_fmt($kpi['revenue']) ?> ฿</div>
      </div>
      <div class="tile" title="Average Order Value">
        <div class="ico"><i class="bi bi-graph-up"></i></div>
        <div class="l"><i class="bi bi-calculator"></i> มูลค่าเฉลี่ย/ออเดอร์</div>
        <div class="n">
          <?= (int)$kpi['orders_count']>0 ? money_fmt(((float)$kpi['revenue'])/(int)$kpi['orders_count']) : '0.00' ?> ฿
        </div>
      </div>
      <div class="tile" title="รายได้ที่เกิดจากท็อปปิงทั้งหมด">
        <div class="ico"><i class="bi bi-stars"></i></div>
        <div class="l"><i class="bi bi-plus-circle"></i> รายได้จากท็อปปิง</div>
        <div class="n"><?= money_fmt($extra['topping_total'] ?? 0) ?> ฿</div>
      </div>
      <div class="tile" title="ส่วนลดที่มอบให้จากโปรโมชัน">
        <div class="ico"><i class="bi bi-ticket-perforated"></i></div>
        <div class="l"><i class="bi bi-tags-fill"></i> ส่วนลดที่ให้ไป (โปรโมชัน)</div>
        <div class="n" style="color:#ffb4b0">-<?= money_fmt($extra['discount_total'] ?? 0) ?> ฿</div>
      </div>
    </div>
  </div>

  <!-- Top items + charts -->
  <div class="row">
    <div class="col-lg-7 mb-3">
      <div class="cardx h-100">
        <div class="card-head d-flex align-items-center justify-content-between">
          <div class="font-weight-bold"><i class="bi bi-trophy"></i> เมนูขายดี (Top <?= $TOP_N ?>)</div>
          <span class="badge badge-lightx"><i class="bi bi-slash-circle"></i> ไม่รวมออเดอร์ยกเลิก</span>
        </div>
        <div class="p-3">
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
                    <td><i class="bi bi-cup-hot"></i> <?= htmlspecialchars($i['name'],ENT_QUOTES,'UTF-8') ?></td>
                    <td class="text-right"><?= (int)$i['qty'] ?></td>
                    <td class="text-right"><?= money_fmt($i['sales']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5 mb-3">
      <div class="cardx h-100">
        <div class="card-head"><div class="font-weight-bold"><i class="bi bi-bar-chart"></i> กราฟภาพรวม</div></div>
        <div class="p-3">
          <canvas id="chartTop" height="180" aria-label="Top5 items chart"></canvas>
          <hr style="border-color:rgba(255,255,255,.10)">
          <canvas id="chartStatus" height="160" aria-label="Order status chart"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Top 5 bar — ใช้ฟ้าโทนเดียวกับหน้า front_store
const topLabels = <?= json_encode(array_column($chartItems,'name'), JSON_UNESCAPED_UNICODE) ?>;
const topQty    = <?= json_encode(array_map('intval', array_column($chartItems,'qty'))) ?>;
new Chart(document.getElementById('chartTop'), {
  type: 'bar',
  data: { labels: topLabels, datasets: [{
    label: 'จำนวน (แก้ว)',
    data: topQty,
    backgroundColor: 'rgba(58,163,255,0.35)',
    borderColor: '#3aa3ff',
    borderWidth: 1.2
  }]},
  options: {
    plugins:{ legend:{ display:false } },
    scales:{
      x:{ ticks:{ color:'#b9c6d6' }, grid:{ color:'rgba(255,255,255,.08)' } },
      y:{ beginAtZero:true, ticks:{ color:'#b9c6d6' }, grid:{ color:'rgba(255,255,255,.08)' } }
    }
  }
});

// Status donut — โทนฟ้าสามเฉด + เขียว/แดงเสริม
const stLabels = <?= json_encode(array_column($byStatus,'status'), JSON_UNESCAPED_UNICODE) ?>;
const stData   = <?= json_encode(array_map('intval', array_column($byStatus,'c'))) ?>;
new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: stLabels,
    datasets: [{
      data: stData,
      backgroundColor: ['#3aa3ff','#7cbcfd','#a9cffd','#e53935','#22c55e','#8899aa'],
      borderColor: 'transparent'
    }]
  },
  options: {
    plugins:{ legend:{ position:'bottom', labels:{ color:'#e9eef6' } } }
  }
});
</script>
</body>
</html>

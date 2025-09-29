<?php
// SelectRole/check_order.php — แสดงออเดอร์ทั้งหมด + ดูสลิป (modal)
// โทนเดียวกับ front_store + สีข้อความ chips: ขนาด/หวาน/น้ำแข็ง/ท็อปปิง/โปรฯ
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_fmt($n){ return number_format((float)$n, 2); }

/* --------- รับค่าตัวกรอง --------- */
$status    = $_GET['status']     ?? 'all';
$q         = trim((string)($_GET['q'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$time_from = trim((string)($_GET['time_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));
$time_to   = trim((string)($_GET['time_to'] ?? ''));

$dt_from = $date_from ? ($date_from.' '.($time_from ?: '00:00:00')) : '';
$dt_to   = $date_to   ? ($date_to  .' '.($time_to   ?: '23:59:59')) : '';

/* ✅ Quick Filter active */
$active_qf = '';
$today = (new DateTime('today'))->format('Y-m-d');
$sevenStart = (new DateTime('today -6 days'))->format('Y-m-d');
$tf = $time_from ?: '00:00';
$tt = $time_to ?: '23:59';
if ($date_from==='' && $date_to==='' && $time_from==='' && $time_to==='') {
  $active_qf = 'all';
} elseif ($date_from===$today && $date_to===$today && $tf==='00:00' && $tt==='23:59') {
  $active_qf = 'today';
} elseif ($date_from===$sevenStart && $date_to===$today && $tf==='00:00' && $tt==='23:59') {
  $active_qf = '7d';
}

/* --------- Query orders --------- */
$where  = '1=1'; $types=''; $params=[];
if ($status !== 'all') { $where .= ' AND o.status = ?'; $types.='s'; $params[]=$status; }
if ($dt_from !== '')   { $where .= ' AND o.order_time >= ?'; $types.='s'; $params[]=$dt_from; }
if ($dt_to !== '')     { $where .= ' AND o.order_time <= ?'; $types.='s'; $params[]=$dt_to; }
if ($q !== '') {
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
         u.username, u.name,
         COALESCE(ps.slip_count, 0) AS slip_count,
         COALESCE(items.item_count, 0) AS item_count
  FROM orders o
  JOIN users u ON u.user_id=o.user_id
  LEFT JOIN (
    SELECT order_id, COUNT(*) AS slip_count FROM payment_slips GROUP BY order_id
  ) ps ON ps.order_id = o.order_id
  LEFT JOIN (
    SELECT order_id, SUM(quantity) AS item_count FROM order_details GROUP BY order_id
  ) items ON items.order_id = o.order_id
  WHERE $where
  ORDER BY o.order_time DESC
";
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$orders_rs = $stmt->get_result();

$orders = []; $order_ids = [];
while ($row = $orders_rs->fetch_assoc()) { $orders[] = $row; $order_ids[] = (int)$row['order_id']; }
$stmt->close();

/* --------- รายการย่อย --------- */
$details = [];
if (!empty($order_ids)) {
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types_in = str_repeat('i', count($order_ids));
  $sql2 = "
    SELECT d.order_id, d.menu_id, d.quantity, d.note, d.total_price,
           d.promo_id,
           m.name AS menu_name, m.price AS unit_base_price,
           p.name AS promo_name, p.discount_type, p.discount_value, p.max_discount
    FROM order_details d
    JOIN menu m ON m.menu_id = d.menu_id
    LEFT JOIN promotions p ON p.promo_id = d.promo_id
    WHERE d.order_id IN ($in)
    ORDER BY d.order_detail_id
  ";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param($types_in, ...$order_ids);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  while ($r = $res2->fetch_assoc()) {
    $qty         = max(1, (int)$r['quantity']);
    $line_total  = (float)$r['total_price'];
    $unit_final  = $line_total / $qty;
    $base_price  = (float)$r['unit_base_price'];

    $unit_discount = 0.0;
    if (!is_null($r['promo_id'])) {
      $raw = ((string)$r['discount_type'] === 'PERCENT')
        ? ((float)$r['discount_value']/100.0) * $base_price
        : (float)$r['discount_value'];
      $cap = is_null($r['max_discount']) ? 999999999.0 : (float)$r['max_discount'];
      $unit_discount = max(0.0, min($raw, $cap));
    }

    $topping_per_unit = max(0.0, $unit_final - max(0.0, $base_price - $unit_discount));
    $topping_line     = $topping_per_unit * $qty;

    $r['calc_unit_final']     = $unit_final;
    $r['calc_unit_discount']  = $unit_discount;
    $r['calc_topping_unit']   = $topping_per_unit;
    $r['calc_topping_line']   = $topping_line;

    $details[$r['order_id']][] = $r;
  }
  $stmt2->close();
}

/* --------- สลิป --------- */
$slips = [];
if (!empty($order_ids)) {
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types_in = str_repeat('i', count($order_ids));
  $sql3 = "
    SELECT order_id, file_path, mime, uploaded_at
    FROM payment_slips
    WHERE order_id IN ($in)
    ORDER BY uploaded_at DESC
  ";
  $stmt3 = $conn->prepare($sql3);
  $stmt3->bind_param($types_in, ...$order_ids);
  $stmt3->execute();
  $res3 = $stmt3->get_result();
  while ($r = $res3->fetch_assoc()) {
    $oid = (int)$r['order_id'];
    $url = '../' . ltrim((string)$r['file_path'], '/');
    $slips[$oid][] = [
      'path' => $url,
      'mime' => (string)$r['mime'],
      'uploaded_at' => (string)$r['uploaded_at'],
    ];
  }
  $stmt3->close();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เช็คออเดอร์ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<style>
/* ========= Base Tokens ========= */
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg-grad1:#222831; --bg-grad2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --ink:#F4F7F8; --ink-muted:#CFEAED;
  --brand-900:#EEEEEE; --brand-700:#BFC6CC; --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
  --aqua-500:#00ADB5; --aqua-400:#5ED8DD; --mint-300:#223037; --violet-200:#5C6A74;
  --ok:#2ecc71; --warn:#f0ad4e; --bad:#d9534f;
  --shadow-lg:0 22px 66px rgba(0,0,0,.55); --shadow:0 14px 32px rgba(0,0,0,.42);
}

/* ========= Layout / Cards ========= */
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--ink); font-family:"Segoe UI", Tahoma, sans-serif; min-height:100vh;
}
.wrap{max-width:1320px; margin:28px auto; padding:0 16px;}
.topbar{
  position:sticky; top:0; z-index:50; margin-bottom:12px;
  padding:12px 16px; border-radius:14px;
  background:color-mix(in oklab, var(--surface), black 6%);
  border:1px solid color-mix(in oklab, var(--violet-200), black 12%);
  box-shadow:0 8px 20px rgba(0,0,0,.18); backdrop-filter: blur(6px);
}
.brand{font-weight:900; letter-spacing:.3px; color:var(--text-strong); margin:0}
.brand .bi{opacity:.95; margin-right:6px}
.badge-user{ background:linear-gradient(180deg,var(--brand-400),var(--brand-700)); color:#061b22; font-weight:800; border-radius:999px }
.topbar-actions{ gap:8px }
.topbar .btn-primary{ background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800 }

.filter{
  background:color-mix(in oklab, var(--surface), white 8%);
  border:1px solid color-mix(in oklab, var(--violet-200), black 15%);
  border-radius:14px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,.18);
  margin-bottom:16px; color:var(--text-normal);
}
.filter label{font-weight:700; font-size:.9rem; color:var(--text-strong)}
.filter .form-control,.filter .custom-select{
  background:var(--surface-2); color:var(--ink); border-radius:999px;
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
}
.filter .btn-find{font-weight:800; border-radius:999px}
.input-icon{ position:relative }
.input-icon .bi{ position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted) }
.input-icon input{ padding-left:36px }
.quick-filters{ display:flex; flex-wrap:wrap; gap:8px; margin-top:8px }
.quick-filters .btn{ border-radius:999px; font-weight:800; }

.grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap:18px;}
.grid.cols-1{ grid-template-columns: 1fr !important; }
.grid.cols-2{ grid-template-columns: repeat(2, minmax(0,1fr)) !important; }
.grid.cols-3{ grid-template-columns: repeat(3, minmax(0,1fr)) !important; }
@media (max-width:768px){ .grid.cols-1,.grid.cols-2,.grid.cols-3{ grid-template-columns: 1fr !important; } }

.quick-filters .js-cols.active, .quick-filters .qf.active{
  background:linear-gradient(180deg,#3aa3ff,#1f7ee8);
  border-color:#1669c9; color:#061b22;
}

/* Order cards */
.card-order{
  position:relative;
  background:linear-gradient(180deg, color-mix(in oklab, var(--surface), white 12%), color-mix(in oklab, var(--surface-2), white 6%));
  color:var(--ink);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  box-shadow:0 12px 28px rgba(0,0,0,.28), 0 0 0 1px color-mix(in oklab, var(--mint-300), white 60%);
  border-radius:16px; display:flex; flex-direction:column; overflow:hidden;
  transition:transform .15s ease, box-shadow .15s ease, border-color .15s ease;
}
.card-order:hover{ transform:translateY(-2px); border-color:color-mix(in oklab, var(--brand-400), black 8%); box-shadow:0 18px 40px rgba(0,0,0,.32), 0 0 0 1px color-mix(in oklab, var(--brand-400), white 50%); }
.card-order::before{ content:""; position:absolute; inset:0; border-radius:inherit; pointer-events:none; box-shadow:inset 0 1px 0 color-mix(in oklab, var(--brand-900), black 80%); opacity:.65; }
.card-order::after{ content:""; position:absolute; left:0; right:0; top:0; height:3px; background:linear-gradient(90deg, var(--brand-500), var(--brand-400)); opacity:.35; }

.ribbon{ position:absolute; left:-6px; top:10px; background:#1f8bff; color:#fff; padding:6px 12px; font-weight:900; font-size:.8rem; border-radius:0 10px 10px 0; box-shadow:0 10px 22px rgba(0,0,0,.35) }
.ribbon.ready{ background:#2e7d32 } .ribbon.canceled{ background:#d9534f } .ribbon.pending{ background:#f0ad4e; color:#113 }
.id-badge{ position:absolute; right:10px; top:10px; background:color-mix(in oklab, var(--surface-2), white 10%); color:var(--ink); border:1px solid color-mix(in oklab, var(--brand-700), black 14%); padding:4px 10px; border-radius:999px; font-weight:900; font-size:.85rem; box-shadow:0 6px 14px rgba(0,0,0,.18); letter-spacing:.2px; }

.co-head{ padding:12px 16px; background:color-mix(in oklab, var(--surface-2), white 8%); border-bottom:1px solid color-mix(in oklab, var(--brand-700), black 18%); display:flex; justify-content:space-between; align-items:center; }
.oid{font-weight:900; font-size:1.05rem; display:flex; align-items:center; gap:8px}
.copy{ cursor:pointer; color:var(--brand-300); font-size:1rem }
.meta{font-size:.82rem; color:var(--ink-muted)}
.badges{ display:flex; gap:8px; align-items:center; flex-wrap:wrap }
.badge-status{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:800; background:var(--surface) }
.st-pending{color:var(--warn)} .st-ready{color:var(--ok)} .st-canceled{color:var(--bad)}
.badge-pay{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:800; background:var(--surface); color:var(--ink); border:1px solid color-mix(in oklab, var(--brand-700), black 18%) }
.pay-cash{ color:#7dffa3 } .pay-transfer{ color:#9fd8ff }
.dot{width:8px; height:8px; border-radius:50%; background:currentColor}

.co-body{padding:14px 16px; flex:1}
.line{margin-bottom:12px; font-size:.95rem; display:flex; justify-content:space-between; gap:10px}
.qtyname{font-weight:800; color:var(--text-strong)}
.money{font-weight:900; color:var(--brand-300); white-space:nowrap; text-shadow:0 0 1px rgba(0,0,0,.25)}
.note{
  margin-top:6px; font-size:.83rem; color:var(--ink);
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%); border-radius:8px; padding:6px 8px; display:inline-block;
}
.meta2{ display:flex; flex-wrap:wrap; gap:6px; margin-top:8px }
.chip{
  display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px;
  font-size:.8rem; font-weight:800;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  color:var(--text-normal);
}
.chip-top{ background:color-mix(in oklab, var(--aqua-400), black 82%); color:#dffbfd; border-color:color-mix(in oklab, var(--aqua-400), black 55%); }
.chip-promo{ background:color-mix(in oklab, var(--ok), black 82%); color:#e7ffef; border-color:color-mix(in oklab, var(--ok), black 55%); }
.chip .bi{ opacity:.9 }
.divider{border-top:1px dashed color-mix(in oklab, var(--brand-700), black 18%); margin:8px 0}

.co-foot{
  background:linear-gradient(180deg, color-mix(in oklab, var(--surface-2), white 6%), color-mix(in oklab, var(--surface-3), white 3%));
  color:var(--ink-muted); padding:12px 16px; display:flex; justify-content:space-between; align-items:center;
  border-top:1px solid color-mix(in oklab, var(--brand-700), black 18%); border-radius:0 0 16px 16px
}
.sum-l{font-weight:700} .sum-r{font-size:1.1rem; font-weight:900; color:var(--ink)}

/* modal */
#slipModalBackdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; z-index:1050; }
#slipModal{ position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:min(900px, 96vw); max-height:92vh; overflow:auto;
  background:var(--surface); color:var(--ink); border-radius:14px; box-shadow:var(--shadow-lg); display:none; z-index:1060; }
#slipModal .head{ display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid color-mix(in oklab, var(--brand-700), black 18%); background:color-mix(in oklab, var(--surface-2), white 6%); font-weight:800;}
#slipModal .body{ padding:12px; }
.slip-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:12px;}
.slip-item{ background:color-mix(in oklab, var(--surface-2), white 6%); border:1px solid color-mix(in oklab, var(--brand-700), black 20%); border-radius:10px; padding:8px; text-align:center; }
.slip-item img{ max-width:100%; height:auto; border-radius:8px; cursor:zoom-in; }
.slip-meta{ font-size:.8rem; color:var(--ink-muted); margin-top:6px }
.btn-close-slim{ background:transparent; border:0; font-size:26px; line-height:1; cursor:pointer; color:var(--ink);}
.btn-view-slip{ border-radius:999px; font-weight:800; border:1px solid color-mix(in oklab, var(--brand-700), black 18%); color:var(--ink); background:var(--surface);}
.btn-view-slip:hover{ background:color-mix(in oklab, var(--surface-2), white 4%); }

#toTop{ position:fixed; right:18px; bottom:18px; z-index:2000; display:none; }
#toTop .btn{ border-radius:999px; font-weight:900; box-shadow:0 10px 24px rgba(0,0,0,.25) }

/* ============================
   Minimal Theme match front_store
   ============================ */
:root{
  --bg-grad1:#11161b; --bg-grad2:#141b22;
  --surface:#1a2230;  --surface-2:#192231; --surface-3:#202a3a;
  --ink:#e9eef6; --ink-muted:#b9c6d6; --text-strong:#ffffff;
  --brand-500:#3aa3ff; --brand-400:#7cbcfd; --brand-300:#a9cffd;
  --radius:10px; --shadow:none; --shadow-lg:none;
}
body,.table,.btn,input,label,.badge,.chip,.note{ font-size:14.5px !important; }
.topbar,.filter,.card-order,#slipModal{ background: var(--surface) !important; border: 1px solid rgba(255,255,255,.08) !important; border-radius: var(--radius) !important; box-shadow: none !important; }
.co-head,.co-foot{ background: var(--surface-2) !important; border-color: rgba(255,255,255,.10) !important; }
.badge-user{ background:#2ea7ff !important; color:#082238 !important; border:1px solid #1669c9 !important; border-radius:999px !important; font-weight:800 !important; }
.btn-primary,.quick-filters .js-cols.active,.quick-filters .qf.active{ background: var(--brand-500) !important; border:1px solid #1e6acc !important; color:#fff !important; font-weight:800 !important; }
.btn-primary:hover{ filter:brightness(1.06) }
.btn-outline-light,.btn-view-slip{ background: transparent !important; color: var(--ink) !important; border: 1px solid rgba(255,255,255,.18) !important; }
.filter{ color: var(--ink) !important; }
.filter label{ color: var(--text-strong) !important; }
.filter .form-control,.filter .custom-select{ background: var(--surface-3) !important; color: var(--ink) !important; border: 1px solid rgba(255,255,255,.10) !important; border-radius: 12px !important; }
.input-icon .bi{ color: var(--ink-muted) !important; }
.card-order{ background: var(--surface) !important; border: 1px solid rgba(255,255,255,.08) !important; }
.qtyname{ color:#fff !important; font-weight:800 !important; }
.money{ color:#fff !important; text-shadow:none !important; }
.note{ background: var(--surface-3) !important; border: 1px solid rgba(255,255,255,.10) !important; color:#fff !important; border-radius: 10px !important; }
.meta2 .chip{ background: var(--surface-3) !important; border: 1px solid rgba(255,255,255,.12) !important; border-radius: 12px !important; }

/* ==== สีตัวอักษรชิปแบบหน้า front_store (ให้ specificity สูงและ !important) ==== */
.card-order .chip{ color:#cfd7e5; font-weight:800; }
.card-order .chip.chip-size{  color:#e6edf7 !important; }     /* ขนาด */
.card-order .chip.chip-sweet{ color:#e6edf7 !important; }     /* หวาน */
.card-order .chip.chip-ice{   color:#8fc6ff !important; }     /* น้ำแข็ง */
.card-order .chip.chip-toplist{ color:#66f0d9 !important; }   /* ท็อปปิง (รายการ) */
.card-order .chip.chip-topamt{ color:#ffffff !important; font-weight:900 !important; } /* ท็อปปิง (+ราคา/หน่วย) */

.badge-status,.badge-pay{ background: var(--surface-3) !important; border: 1px solid rgba(255,255,255,.12) !important; color: var(--ink) !important; }
.ribbon{ background: var(--brand-500) !important; box-shadow:none !important; }
.ribbon.ready{ background:#22c55e !important; }
.ribbon.canceled{ background:#d9534f !important; }
.ribbon.pending{ background:#f0ad4e !important; color:#08121f !important; }
#slipModal .head{ background: var(--surface-2) !important; border-color: rgba(255,255,255,.10) !important; }
.slip-item{ background: var(--surface-3) !important; border: 1px solid rgba(255,255,255,.12) !important; }
.btn-close-slim{ color:#fff !important; }
#toTop .btn{ background: var(--brand-500) !important; border: 1px solid #1e6acc !important; color:#fff !important; font-weight:900 !important; box-shadow:none !important; }
</style>
</head>
<body>
<div class="wrap">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between">
    <h4 class="brand"><i class="bi bi-clipboard2-check"></i> PSU Blue Cafe • เช็คออเดอร์</h4>
    <div class="d-flex align-items-center topbar-actions">
      <a href="../front_store/front_store.php" class="btn btn-primary btn-sm"><i class="bi bi-shop"></i> หน้าร้าน</a>
      <a href="../SelectRole/role.php" class="btn btn-primary btn-sm"><i class="bi bi-person-badge"></i> บทบาท</a>
      <a href="user_profile.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800"><i class="bi bi-person-circle"></i> ข้อมูลส่วนตัว</a>
      <span class="badge badge-user px-3 py-2"><i class="bi bi-person"></i> ผู้ใช้: <?= h($_SESSION['username'] ?? '') ?></span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <!-- Filter -->
  <form class="filter" method="get">
    <div class="form-row">
      <div class="col-md-2 mb-2">
        <label><i class="bi bi-funnel"></i> สถานะ</label>
        <select name="status" class="custom-select">
          <?php foreach(['all'=>'(ทั้งหมด)','pending'=>'Pending','ready'=>'Ready','canceled'=>'Canceled'] as $k=>$v){
            $sel = ($status===$k)?'selected':''; echo '<option value="'.h($k).'" '.$sel.'>'.h($v).'</option>';
          } ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label><i class="bi bi-search"></i> ค้นหาชื่อเมนู</label>
        <div class="input-icon">
          <i class="bi bi-search"></i>
          <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="เช่น ชาไทย">
        </div>
      </div>
      <div class="col-md-3 mb-2">
        <label><i class="bi bi-calendar2-week"></i> ตั้งแต่ (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col input-icon">
            <i class="bi bi-calendar"></i>
            <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>">
          </div>
          <div class="col input-icon">
            <i class="bi bi-clock"></i>
            <input type="time" name="time_from" class="form-control" value="<?= h($time_from) ?>">
          </div>
        </div>
      </div>
      <div class="col-md-3 mb-2">
        <label><i class="bi bi-calendar2-week"></i> ถึง (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col input-icon">
            <i class="bi bi-calendar"></i>
            <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>">
          </div>
          <div class="col input-icon">
            <i class="bi bi-clock"></i>
            <input type="time" name="time_to" class="form-control" value="<?= h($time_to) ?>">
          </div>
        </div>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <button class="btn btn-primary btn-block btn-find"><i class="bi bi-arrow-right-circle"></i> ค้นหา</button>
      </div>
    </div>

    <div class="quick-filters">
      <button type="button" class="btn btn-light btn-sm qf <?= $active_qf==='today'?'active':'' ?>" data-qf="today">
        <i class="bi bi-calendar-day"></i> วันนี้
      </button>
      <button type="button" class="btn btn-light btn-sm qf <?= $active_qf==='7d'?'active':'' ?>" data-qf="7d">
        <i class="bi bi-calendar-range"></i> 7 วัน
      </button>
      <button type="button" class="btn btn-light btn-sm qf <?= $active_qf==='all'?'active':'' ?>" data-qf="all">
        <i class="bi bi-infinity"></i> ทั้งหมด
      </button>

      <!-- ปุ่มสลับจำนวนคอลัมน์ -->
      <div class="btn-group ml-2" role="group" aria-label="ปรับจำนวนคอลัมน์">
        <button type="button" class="btn btn-outline-light btn-sm js-cols" data-cols="1">1 คอลัมน์</button>
        <button type="button" class="btn btn-outline-light btn-sm js-cols" data-cols="2">2 คอลัมน์</button>
        <button type="button" class="btn btn-outline-light btn-sm js-cols" data-cols="3">3 คอลัมน์</button>
      </div>
    </div>
    <div class="text-light small mt-1">* ใส่เฉพาะวันที่ได้ ระบบจะถือเป็น 00:00 ถึง 23:59</div>
  </form>

  <?php if(!empty($orders)): ?>
    <div class="grid" id="orderGrid">
      <?php foreach($orders as $o):
        $statusClass = ($o['status']==='ready'?'st-ready':($o['status']==='canceled'?'st-canceled':'st-pending'));
        $ribbonClass = ($o['status']==='ready'?'ready':($o['status']==='canceled'?'canceled':'pending'));
        $rows = $details[$o['order_id']] ?? [];
        $is_transfer = ((int)$o['slip_count'] > 0);
        $pay_text = $is_transfer ? 'โอนเงิน' : 'เงินสด';
        $pay_class = $is_transfer ? 'pay-transfer' : 'pay-cash';
        $oid = (int)$o['order_id'];
        $mySlips = $slips[$oid] ?? [];
        $itemCount = (int)($o['item_count'] ?? 0);
      ?>
      <div class="card-order" data-status="<?= h($o['status']) ?>">
        <div class="ribbon <?= $ribbonClass ?>">
          <?php if($o['status']==='ready'): ?>
            <i class="bi bi-check2-circle"></i> Ready
          <?php elseif($o['status']==='canceled'): ?>
            <i class="bi bi-x-octagon"></i> Canceled
          <?php else: ?>
            <i class="bi bi-hourglass-split"></i> Pending
          <?php endif; ?>
        </div>

        <div class="id-badge">#<?= $oid ?></div>

        <div class="co-head">
          <div>
            <div class="oid">
              #<?= $oid ?>
              <i class="bi bi-clipboard-plus copy" title="คัดลอกเลขออเดอร์" data-copy="<?= $oid ?>"></i>
            </div>
            <div class="meta"><i class="bi bi-clock-history"></i> <?= h($o['order_time']) ?> • <i class="bi bi-basket2"></i> <?= $itemCount ?> รายการ</div>
          </div>
          <div class="badges">
            <div class="badge-pay <?= $pay_class ?>" title="<?= $is_transfer ? 'มีสลิปแนบ' : 'ไม่มีสลิป (เงินสด)' ?>">
              <i class="bi <?= $is_transfer ? 'bi-credit-card-2-back' : 'bi-cash-coin' ?>"></i>
              <?= h($pay_text) ?>
              <?php if ($is_transfer): ?><span class="text-primary ml-1">(<?= (int)$o['slip_count'] ?>)</span><?php endif; ?>
            </div>

            <?php if (!empty($mySlips)): ?>
              <button class="btn btn-sm btn-view-slip" data-oid="<?= $oid ?>" type="button">
                <i class="bi bi-images"></i> ดูสลิป (<?= (int)count($mySlips) ?>)
              </button>
            <?php endif; ?>

            <div class="badge-status <?= $statusClass ?>">
              <span class="dot"></span>
              <i class="bi <?= $o['status']==='ready' ? 'bi-check-circle' : ($o['status']==='canceled'?'bi-x-circle':'bi-hourglass') ?>"></i>
              <?= h(ucfirst($o['status'])) ?>
            </div>
          </div>
        </div>

        <div class="co-body">
          <?php if(!empty($rows)): foreach($rows as $r):
            $qty          = max(1, (int)$r['quantity']);
            $unit_final   = (float)$r['calc_unit_final'];
            $unit_disc    = (float)$r['calc_unit_discount'];
            $top_unit     = (float)$r['calc_topping_unit'];

            $promo_label  = '';
            if (!is_null($r['promo_id'])) {
              if ((string)$r['discount_type'] === 'PERCENT') {
                $pct = rtrim(rtrim(number_format((float)$r['discount_value'],2,'.',''), '0'), '.');
                $promo_label = $r['promo_name'] . " • ลด {$pct}% (−" . money_fmt($unit_disc) . " ฿/ชิ้น)";
              } else {
                $promo_label = $r['promo_name'] . " • ลด −" . money_fmt($unit_disc) . " ฿/ชิ้น";
              }
            }
          ?>
            <div class="line">
              <div class="flex-grow-1">
                <div class="qtyname"><i class="bi bi-cup-hot"></i> <?= (int)$qty ?> × <?= h($r['menu_name']) ?></div>

                <?php if(!empty($r['note'])): ?>
                  <div class="note"><i class="bi bi-sticky"></i> <?= h($r['note']) ?></div>
                <?php endif; ?>

                <div class="meta2">
                  <?php if ($top_unit > 0): ?>
                    <span class="chip chip-top chip-topamt"><i class="bi bi-egg-fried"></i> ท็อปปิง: +<?= money_fmt($top_unit) ?> ฿/หน่วย</span>
                  <?php endif; ?>
                  <?php if ($promo_label !== ''): ?>
                    <span class="chip chip-promo"><i class="bi bi-stars"></i> โปรฯ: <?= h($promo_label) ?></span>
                  <?php endif; ?>
                  <?php if ($top_unit <= 0 && $promo_label === ''): ?>
                    <span class="chip"><i class="bi bi-dash-circle"></i> ไม่มีโปร/ท็อปปิง</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="money"><?= money_fmt($r['total_price']) ?> ฿</div>
            </div>
          <?php endforeach; else: ?>
            <div class="text-muted">ไม่มีรายการอาหาร</div>
          <?php endif; ?>
          <div class="divider"></div>
        </div>

        <div class="co-foot">
          <div class="sum-l"><i class="bi bi-calculator"></i> รวมทั้งออเดอร์</div>
          <div class="sum-r"><?= money_fmt($o['total_price']) ?> ฿</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center"><i class="bi bi-emoji-neutral"></i> ไม่พบออเดอร์ตามเงื่อนไข</div>
  <?php endif; ?>

</div>

<!-- Back to top -->
<div id="toTop"><button class="btn btn-primary"><i class="bi bi-arrow-up"></i></button></div>

<!-- Modal แสดงสลิป -->
<div id="slipModalBackdrop"></div>
<div id="slipModal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="head">
    <div class="ttl"><i class="bi bi-receipt"></i> สลิปการโอน • ออเดอร์ <span id="mdlOid"></span></div>
    <button class="btn-close-slim" id="btnSlipClose" aria-label="Close">&times;</button>
  </div>
  <div class="body">
    <div id="slipContainer" class="slip-grid"></div>
  </div>
</div>

<script>
(function(){
  // ===== Map slips to JS =====
  const slipMap = <?php
    $out = [];
    foreach ($slips as $oid => $arr) {
      foreach ($arr as $s) { $out[$oid][] = ['path'=>$s['path'], 'uploaded_at'=>$s['uploaded_at']]; }
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  ?>;

  const backdrop = document.getElementById('slipModalBackdrop');
  const modal    = document.getElementById('slipModal');
  const mdlOid   = document.getElementById('mdlOid');
  const listBox  = document.getElementById('slipContainer');
  const btnClose = document.getElementById('btnSlipClose');

  function openModal(oid){
    const items = slipMap[String(oid)] || slipMap[oid] || [];
    mdlOid.textContent = '#' + oid;
    listBox.innerHTML = '';
    if (!items.length) {
      listBox.innerHTML = '<div class="text-muted">ไม่มีสลิป</div>';
    } else {
      for (const it of items) {
        const card = document.createElement('div');
        card.className = 'slip-item';
        const a = document.createElement('a');
        a.href = it.path; a.target = '_blank'; a.rel = 'noopener';
        const img = document.createElement('img');
        img.src = it.path;
        a.appendChild(img);
        const meta = document.createElement('div');
        meta.className = 'slip-meta';
        meta.textContent = 'อัปโหลด: ' + (it.uploaded_at || '');
        card.appendChild(a); card.appendChild(meta);
        listBox.appendChild(card);
      }
    }
    backdrop.style.display = 'block';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeModal(){ backdrop.style.display='none'; modal.style.display='none'; document.body.style.overflow=''; }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.btn-view-slip');
    if (btn) { openModal(btn.getAttribute('data-oid')); }
    const cp = e.target.closest('.copy');
    if (cp) {
      const v = cp.getAttribute('data-copy') || '';
      navigator.clipboard?.writeText(v).then(()=>{
        cp.classList.remove('bi-clipboard-plus'); cp.classList.add('bi-clipboard-check');
        setTimeout(()=>{ cp.classList.remove('bi-clipboard-check'); cp.classList.add('bi-clipboard-plus'); }, 1200);
      }).catch(()=>{});
    }
  });
  backdrop.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeModal(); });

  // ===== Quick filters =====
  document.querySelectorAll('[data-qf]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const type = btn.getAttribute('data-qf');
      const set = (name, v)=>{ const el = document.querySelector(`[name="${name}"]`); if(el){ el.value=v; } };
      const now = new Date();
      const toDateStr = d => d.toISOString().slice(0,10);
      if (type==='today') {
        const d = toDateStr(now);
        set('date_from', d); set('time_from','00:00'); set('date_to', d); set('time_to','23:59');
      } else if (type==='7d') {
        const d2 = toDateStr(now);
        const d1 = new Date(now.getTime() - 6*24*3600*1000);
        set('date_from', toDateStr(d1)); set('time_from','00:00'); set('date_to', d2); set('time_to','23:59');
      } else {
        set('date_from',''); set('time_from',''); set('date_to',''); set('time_to','');
      }
      document.querySelector('form.filter').submit();
    });
  });

  // ===== Back to top =====
  const toTop = document.getElementById('toTop');
  window.addEventListener('scroll', ()=>{ toTop.style.display = window.scrollY > 400 ? 'block' : 'none'; });
  toTop.querySelector('button').addEventListener('click', ()=> window.scrollTo({top:0, behavior:'smooth'}) );

  // ===== Toggle Columns (1/2/3) =====
  const gridEl = document.getElementById('orderGrid');
  function applyCols(n){
    if(!gridEl) return;
    gridEl.classList.remove('cols-1','cols-2','cols-3');
    if(n === 1) gridEl.classList.add('cols-1');
    if(n === 2) gridEl.classList.add('cols-2');
    if(n === 3) gridEl.classList.add('cols-3');
    localStorage.setItem('orderGridCols', String(n));
    document.querySelectorAll('.js-cols').forEach(btn=>{
      const on = btn.getAttribute('data-cols') === String(n);
      btn.classList.toggle('active', on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }
  document.querySelectorAll('.js-cols').forEach(btn=>{
    btn.addEventListener('click', ()=> applyCols(parseInt(btn.getAttribute('data-cols'),10)));
  });
  let savedCols = parseInt(localStorage.getItem('orderGridCols')||'0',10);
  if(savedCols!==1 && savedCols!==2 && savedCols!==3){ savedCols = 1; }
  applyCols(savedCols);
})();
</script>

<script>
/* ===== ใส่สีข้อความชิปแบบเดียวกับหน้า front_store =====
   - สแกนทุก .chip ภายใต้ .card-order (ไม่จำกัดเฉพาะ .meta2)
   - รองรับรูปแบบข้อความยืดหยุ่น, เว้นวรรค, ตัวเล็ก/ใหญ่
   - ท็อปปิงที่เป็น “+ราคา/หน่วย” จะได้คลาส chip-topamt (ตัวขาวหนา)
*/
(function(){
  const chipEls = document.querySelectorAll('.card-order .chip');
  const reSize  = /(ขนาด|size)\s*:/i;
  const reSweet = /(หวาน|sweet(ness)?)\s*:/i;
  const reIce   = /(น้ำแข็ง|ice)\s*:/i;
  const reTop   = /(ท็อปปิ?ง์?|topping)/i;
  const reTopAmt= /[+＋]\s*\d|฿\s*\/?\s*(หน่วย|ชิ้น)/i;

  chipEls.forEach(el=>{
    const t = (el.textContent || '').trim();
    if (reSize.test(t))       el.classList.add('chip-size');
    else if (reSweet.test(t)) el.classList.add('chip-sweet');
    else if (reIce.test(t))   el.classList.add('chip-ice');
    else if (reTop.test(t))   el.classList.add( reTopAmt.test(t) ? 'chip-topamt' : 'chip-toplist' );
  });
})();
</script>
</body>
</html>

<?php
// user_profile.php — โปรไฟล์ผู้ใช้: สรุปชั่วโมงทำงานของตัวเอง (ทุน/ปกติ) + คิดเงินชั่วโมงปกติ + โชว์เวลาปัจจุบันถ้ากำลังทำงานอยู่
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// ----- ตั้งค่าเวลาไทยทั้ง PHP/MySQL -----
date_default_timezone_set('Asia/Bangkok');
$conn->query("SET time_zone = '+07:00'");

// ----- ค่าตอบแทนชั่วโมงปกติ -----
$NORMAL_RATE = 25.0; // บาท/ชั่วโมง

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtHM(int $sec): string {
  if ($sec <= 0) return '0:00 ชม.';
  $h = intdiv($sec, 3600);
  $m = intdiv($sec % 3600, 60);
  return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ชม.';
}
function money2(float $n): string { return number_format($n, 2); }

// ===== ผู้ใช้ปัจจุบัน =====
$uid = (int)$_SESSION['uid'];

// ดึงข้อมูลผู้ใช้ (ชื่อ/รหัส ฯลฯ) เพื่อแสดงหัวกระดาน
$stmt = $conn->prepare("SELECT user_id, username, name, student_ID, role, status FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { die('ไม่พบผู้ใช้'); }

// ===== รับช่วงวันที่ (default = เดือนนี้ ถึง วันนี้) =====
$today  = (new DateTime('today'))->format('Y-m-d');
$month1 = (new DateTime('first day of this month'))->format('Y-m-d');
$start_date = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $month1;
$end_date   = isset($_GET['end'])   && $_GET['end']   !== '' ? $_GET['end']   : $today;

// ===== รวมเวลาที่ “ปิดงานแล้ว” (เฉพาะบรรทัด time_out <> '00:00:00') =====
$sqlClosed = "
  SELECT hour_type,
         COALESCE(SUM(
           TIMESTAMPDIFF(SECOND,
             CONCAT(date_in,' ',time_in),
             CONCAT(date_out,' ',time_out)
           )
         ),0) AS sec_total
  FROM attendance
  WHERE user_id = ?
    AND time_out <> '00:00:00'
    AND date_in >= ?
    AND date_in <= ?
  GROUP BY hour_type
";
$stmt = $conn->prepare($sqlClosed);
$stmt->bind_param('iss', $uid, $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

$sec_fund_closed   = 0;
$sec_normal_closed = 0;
while ($r = $res->fetch_assoc()) {
  if ($r['hour_type'] === 'fund')   $sec_fund_closed   = (int)$r['sec_total'];
  if ($r['hour_type'] === 'normal') $sec_normal_closed = (int)$r['sec_total'];
}
$stmt->close();

// ===== ตรวจ “กำลังปฏิบัติงานอยู่ตอนนี้” (time_out = '00:00:00') =====
$sqlOpen = "
  SELECT hour_type, date_in, time_in
  FROM attendance
  WHERE user_id = ?
    AND time_out = '00:00:00'
    AND date_in >= ?
    AND date_in <= ?
  ORDER BY attendance_id DESC
";
$stmt = $conn->prepare($sqlOpen);
$stmt->bind_param('iss', $uid, $start_date, $end_date);
$stmt->execute();
$openRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$now = new DateTimeImmutable('now');
$sec_fund_live   = $sec_fund_closed;
$sec_normal_live = $sec_normal_closed;

$open_status = [];
foreach ($openRows as $o) {
  $dtStart = strtotime($o['date_in'].' '.$o['time_in']);
  if ($dtStart) {
    $elapsed = max(0, time() - $dtStart);
    if ($o['hour_type'] === 'fund')   $sec_fund_live   += $elapsed;
    if ($o['hour_type'] === 'normal') $sec_normal_live += $elapsed;
    $open_status[] = [
      'type'   => $o['hour_type'],
      'since'  => $o['date_in'].' '.$o['time_in'],
      'elapsed'=> $elapsed
    ];
  }
}

// ชั่วโมงแบบทศนิยม
$hrs_fund_closed     = round($sec_fund_closed/3600, 2);
$hrs_normal_closed   = round($sec_normal_closed/3600, 2);
$hrs_fund_live       = round($sec_fund_live/3600, 2);
$hrs_normal_live     = round($sec_normal_live/3600, 2);

// คิดค่าตอบแทนชั่วโมงปกติ (รวมงานที่ค้างอยู่ถึงตอนนี้)
$wage_normal_live   = $hrs_normal_live * $NORMAL_RATE;

// ===== รายการบันทึกละเอียด =====
$sqlLogs = "
  SELECT attendance_id, date_in, time_in, date_out, time_out, hour_type
  FROM attendance
  WHERE user_id = ?
    AND date_in >= ?
    AND date_in <= ?
  ORDER BY attendance_id DESC
";
$stmt = $conn->prepare($sqlLogs);
$stmt->bind_param('iss', $uid, $start_date, $end_date);
$stmt->execute();
$logs = $stmt->get_result();
$stmt->close();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>โปรไฟล์ของฉัน • ชั่วโมงทำงาน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ========= Teal-Graphite Tokens ========= */
:root{
  --text-strong:#F4F7F8;
  --text-normal:#E6EBEE;
  --text-muted:#B9C2C9;

  --bg-grad1:#222831;
  --bg-grad2:#393E46;

  --surface:#1C2228;
  --surface-2:#232A31;
  --surface-3:#2B323A;
  --ink:#F4F7F8;
  --ink-muted:#CFEAED;

  --brand-900:#EEEEEE;
  --brand-700:#BFC6CC;
  --brand-500:#00ADB5;
  --brand-400:#27C8CF;
  --brand-300:#73E2E6;

  --ok:#2ecc71; --warn:#f0ad4e;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

html,body{height:100%}
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--ink); font-family:"Segoe UI",Tahoma,Arial,sans-serif
}
.wrap{ max-width:1100px; margin:26px auto; padding:0 14px; }

/* ========== Topbar ========== */
.topbar{
  position:sticky; top:0; z-index:20;
  background:color-mix(in oklab, var(--surface), black 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:14px; padding:12px 16px;
  box-shadow:0 8px 20px rgba(0,0,0,.28);
}
.brand{font-weight:900; letter-spacing:.3px; color:var(--text-strong)}
.badge-chip{
  background:color-mix(in oklab, var(--surface-2), white 6%);
  color:var(--text-strong); border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:999px; padding:.25rem .6rem; font-weight:800
}
.clock{
  display:inline-flex;align-items:center;gap:8px;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:999px;padding:6px 10px;font-weight:800
}
.clock .bi{opacity:.9}
.btn-outline-light{color:var(--text-normal); border-color:color-mix(in oklab, var(--brand-700), black 25%)}

/* ===== สีป้าย "สถานะ" เป็นน้ำเงินตัวอักษรขาว ===== */
.topbar small .badge-chip:nth-of-type(3){
  background: linear-gradient(180deg, #2EA7FF, #1F7EE8) !important;
  border-color: #1669C9 !important;
  color: #fff !important;
  text-shadow: 0 1px 0 rgba(0,0,0,.25);
}

/* ========== Cards / Tiles ========== */
.cardx{
  background:linear-gradient(180deg,
    color-mix(in oklab, var(--surface), white 10%),
    color-mix(in oklab, var(--surface-2), white 6%)
  );
  color:var(--ink);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
  border-radius:16px;
  box-shadow:var(--shadow);
}
.tile{
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:14px; padding:14px 16px;
}
.kpi{ display:grid; grid-template-columns: repeat(3,minmax(200px,1fr)); gap:12px; }
.tile .n{ font-size:1.7rem; font-weight:900; color:var(--brand-300); line-height:1.2; text-shadow:0 0 1px rgba(0,0,0,.25) }
.tile .l{ font-weight:800; color:var(--text-strong); display:flex; align-items:center; gap:8px }
.tile .l .bi{opacity:.9}

/* ===== KPI Tile Colors: 1=ทุน(เขียว), 2=ปกติ(ฟ้า) ===== */
.kpi .tile:nth-child(1){
  background: linear-gradient(180deg, #22C55E, #16A34A) !important;
  border-color: #15803D !important;
}
.kpi .tile:nth-child(1) .l,
.kpi .tile:nth-child(1) .n,
.kpi .tile:nth-child(1) .small{
  color:#fff !important; text-shadow: 0 1px 0 rgba(0,0,0,.25);
}
.kpi .tile:nth-child(2){
  background: linear-gradient(180deg, #2EA7FF, #1F7EE8) !important;
  border-color: #1669C9 !important;
}
.kpi .tile:nth-child(2) .l,
.kpi .tile:nth-child(2) .n,
.kpi .tile:nth-child(2) .small{
  color:#fff !important; text-shadow: 0 1px 0 rgba(0,0,0,.25);
}
.kpi .tile:nth-child(1) .n,
.kpi .tile:nth-child(2) .n{ color:#fff !important; }

/* ========== Pills & table ========== */
.pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:.15rem .6rem;font-weight:800}

/* ชั่วโมงทุน → เขียว */
.pill-fund{
  background: linear-gradient(180deg, #22C55E, #16A34A) !important;
  color:#fff !important;
  border: 1.5px solid #15803D !important;
  text-shadow: 0 1px 0 rgba(0,0,0,.2);
}
/* ชั่วโมงปกติ → น้ำเงิน */
.pill-normal{
  background: linear-gradient(180deg, #2EA7FF, #1F7EE8) !important;
  color:#fff !important;
  border: 1.5px solid #1669C9 !important;
  text-shadow: 0 1px 0 rgba(0,0,0,.2);
}

.blink-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--warn);box-shadow:0 0 0 0 rgba(240,173,78,.7);animation: ping 1.4s infinite}
@keyframes ping{0%{transform:scale(1);opacity:1}70%{box-shadow:0 0 0 10px rgba(240,173,78,0)}100%{transform:scale(1);opacity:1}}

.table thead th{
  background:color-mix(in oklab, var(--surface-3), white 6%);
  color:var(--brand-900);
  border-bottom:2px solid color-mix(in oklab, var(--brand-700), black 22%);
}
.table td,.table th{ border-color:color-mix(in oklab, var(--brand-700), black 22%)!important; color:var(--text-normal) }

/* Alert (live) */
.alert-live{
  background:color-mix(in oklab, var(--warn), black 86%);
  color:#fff3cd;
  border:1px solid color-mix(in oklab, var(--warn), black 55%);
  border-radius:12px
}
.alert-live .bi{opacity:.95}

/* Focus a11y */
:focus-visible{outline:3px solid var(--brand-400); outline-offset:2px; border-radius:10px}

/* Scrollbar */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#3a4752}
*::-webkit-scrollbar-track{background:#151a20}

@media (max-width: 767px){
  .kpi{grid-template-columns: 1fr}
}

/* ====== Toggle บันทึกเวลา: ซ่อนบอดี้เมื่อการ์ดถูก set is-hidden ====== */
#logsCard.is-hidden #logsBody{ display:none; }
#toggleLogsBtn .bi{ margin-right:4px; }
/* ============================
   PSU Blue Cafe • Minimal Theme
   ทำให้ user_profile โทนเดียวกับ front_store
   (วางท้าย <style> เพื่อ override)
   ============================ */

/* --- โทนพื้นฐาน --- */
:root{
  --bg-grad1:#11161b;
  --bg-grad2:#141b22;

  --surface:#1a2230;
  --surface-2:#192231;
  --surface-3:#202a3a;

  --ink:#e9eef6;
  --ink-muted:#b9c6d6;
  --text-strong:#ffffff;

  --brand-500:#3aa3ff;   /* ฟ้าหลัก */
  --brand-400:#7cbcfd;
  --brand-300:#a9cffd;

  --radius:10px;
}

/* พื้นหลัง/ตัวอักษร */
body{
  background: linear-gradient(180deg,var(--bg-grad1),var(--bg-grad2)) !important;
  color: var(--ink) !important;
  letter-spacing:.1px;
}

/* การ์ด / ท็อปบาร์ ให้เรียบ */
.topbar,
.cardx{
  background: var(--surface) !important;
  border: 1px solid rgba(255,255,255,.08) !important;
  border-radius: var(--radius) !important;
  box-shadow: none !important;
}
.co-head,.co-foot{ background: var(--surface-2) !important; }

/* ป้ายผู้ใช้ (badge-chip) ให้ตรง */
.badge-chip{
  background: #2ea7ff !important;
  color: #082238 !important;
  border: 1px solid #1669c9 !important;
  border-radius: 999px !important;
  font-weight: 800 !important;
}

/* ปุ่มหลัก = ฟ้าล้วน */
.btn-primary{
  background: var(--brand-500) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important;
  font-weight:800 !important;
  box-shadow:none !important;
  text-shadow:none !important;
}
.btn-primary:hover{ filter:brightness(1.06) }

/* ปุ่มเส้นขอบบาง */
.btn-outline-light{
  background: transparent !important;
  color: var(--ink) !important;
  border: 1px solid rgba(255,255,255,.18) !important;
  box-shadow:none !important;
}

/* อินพุตวันที่ให้เข้าธีม */
input[type="date"]{
  background: var(--surface-2) !important;
  color: var(--ink) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  border-radius: 10px !important;
}

/* KPI tiles ให้เรียบ (คงสีพิเศษเขียว/ฟ้าตามเดิมได้) */
.tile{
  background: var(--surface-2) !important;
  border: 1px solid rgba(255,255,255,.10) !important;
  border-radius: 14px !important;
  box-shadow: none !important;
}
.tile .n{ color:#fff !important; text-shadow:none !important; }

/* ป้ายชั่วโมง */
.pill{
  border-radius: 999px !important;
  font-weight: 800 !important;
}
.pill-fund{
  background: #22C55E !important;
  border: 1.5px solid #15803D !important;
  color:#fff !important;
}
.pill-normal{
  background: var(--brand-500) !important;
  border: 1.5px solid #1e6acc !important;
  color:#fff !important;
}

/* กล่องแจ้งเตือน live */
.alert-live{
  background: color-mix(in oklab, #f0ad4e, black 86%) !important;
  border: 1px solid color-mix(in oklab, #f0ad4e, black 55%) !important;
  color: #fff3cd !important;
  border-radius: 12px !important;
}

/* ตารางให้เส้นบาง สีจาง */
.table thead th{
  background: var(--surface-2) !important;
  color:#fff !important;
  border-bottom: 1px solid rgba(255,255,255,.10) !important;
}
.table td,.table th{
  border-color: rgba(255,255,255,.08) !important;
  color: var(--ink) !important;
}

/* นาฬิกา (แคปซูล) */
.clock{
  background: var(--surface-3) !important;
  border: 1px solid rgba(255,255,255,.12) !important;
  color: var(--ink) !important;
}

/* ขนาดตัวอักษรให้เสมอ ๆ เหมือนหน้า front_store */
body, .table, .btn, input, label, .badge, .pill{ font-size:14.5px !important; }

</style>
</head>
<body>
<div class="wrap">

  <!-- Top -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div>
      <div class="h5 m-0 brand"><i class="bi bi-person-badge"></i> โปรไฟล์ของฉัน • ชั่วโมงทำงาน</div>
      <small class="text-light">
        <i class="bi bi-person"></i> ชื่อ:
        <span class="badge-chip"><?= h($user['name'] ?: $user['username']) ?></span>
        &nbsp;•&nbsp;<i class="bi bi-pass"></i> รหัสนักศึกษา:
        <span class="badge-chip"><?= h($user['student_ID'] ?: '-') ?></span>
        &nbsp;•&nbsp;<i class="bi bi-activity"></i> สถานะ:
        <span class="badge-chip"><?= h($user['status'] ?: '-') ?></span>
      </small>
    </div>
    <div class="d-flex align-items-center" style="gap:8px">
      <div class="clock"><i class="bi bi-clock"></i><span id="nowClock">--:--:--</span></div>
      <a href="back_store.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-left"></i> กลับหน้าเมนู</a>
    </div>
  </div>

  <!-- ถ้ากำลังปฏิบัติงาน -->
  <?php if (!empty($open_status)): ?>
    <div class="cardx p-3 mb-3 alert-live">
      <div class="font-weight-bold mb-2">
        <span class="blink-dot"></span>
        &nbsp;<i class="bi bi-lightning-charge-fill"></i> กำลังปฏิบัติงานอยู่ตอนนี้
      </div>
      <ul class="mb-0 pl-3">
        <?php foreach($open_status as $o): ?>
          <li class="mb-1">
            <span class="<?= $o['type']==='fund'?'pill pill-fund':'pill pill-normal' ?>">
              <i class="bi bi-briefcase"></i>
              <?= $o['type']==='fund'?'ชั่วโมงทุน':'ชั่วโมงปกติ' ?>
            </span>
            &nbsp;เริ่มเมื่อ: <strong><?= h($o['since']) ?></strong>
            &nbsp;เวลาที่นับถึงตอนนี้: <strong><?= h(fmtHM((int)$o['elapsed'])) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
      <small class="d-block mt-2" style="color:var(--ink-muted)"><i class="bi bi-info-circle"></i> เวลารวมด้านล่าง “รวมถึง” งานที่กำลังทำอยู่ ณ ขณะนี้</small>
    </div>
  <?php endif; ?>

  <!-- Filter -->
  <div class="cardx p-3 mb-3">
    <form class="form-inline" method="get">
      <label class="mr-2" style="color:var(--text-strong)"><i class="bi bi-calendar2-week"></i> ช่วงวันที่</label>
      <input type="date" name="start" class="form-control mr-2"
             style="background:var(--surface-2);color:var(--ink);border:1px solid color-mix(in oklab, var(--brand-700), black 22%);border-radius:10px"
             value="<?= h($start_date) ?>">
      <span class="mr-2 text-light">ถึง</span>
      <input type="date" name="end" class="form-control mr-2"
             style="background:var(--surface-2);color:var(--ink);border:1px solid color-mix(in oklab, var(--brand-700), black 22%);border-radius:10px"
             value="<?= h($end_date) ?>">
      <button class="btn btn-primary"
              style="background:linear-gradient(180deg,var(--brand-500),var(--brand-400));border:0;font-weight:800;border-radius:10px">
              <i class="bi bi-search"></i> แสดง
      </button>
      <span class="ml-3" style="color:var(--ink-muted)"><i class="bi bi-check2-circle"></i> นับงานที่ปิดแล้ว + งานที่กำลังทำ</span>
    </form>
  </div>

  <!-- KPI (Live = รวมจนถึงตอนนี้) -->
  <div class="cardx p-3 mb-3">
    <div class="kpi">
      <div class="tile">
        <div class="l"><i class="bi bi-mortarboard"></i> ชั่วโมงทุน (รวมถึงงานที่กำลังทำ)</div>
        <div class="n"><?= fmtHM($sec_fund_live) ?></div>
        <div class="small mt-1" style="color:var(--ink-muted)"><i class="bi bi-check2"></i> ปิดงานแล้ว: <?= fmtHM($sec_fund_closed) ?> (≈ <?= number_format($hrs_fund_closed,2) ?> ชม.)</div>
      </div>
      <div class="tile">
        <div class="l"><i class="bi bi-briefcase"></i> ชั่วโมงปกติ (รวมถึงงานที่กำลังทำ)</div>
        <div class="n"><?= fmtHM($sec_normal_live) ?></div>
        <div class="small mt-1" style="color:var(--ink-muted)"><i class="bi bi-check2"></i> ปิดงานแล้ว: <?= fmtHM($sec_normal_closed) ?> (≈ <?= number_format($hrs_normal_closed,2) ?> ชม.)</div>
      </div>
      <div class="tile">
        <div class="l"><i class="bi bi-cash-coin"></i> ค่าตอบแทนชั่วโมงปกติ</div>
        <div class="n"><?= money2($wage_normal_live) ?> ฿</div>
        <div class="small mt-1" style="color:var(--ink-muted)"><i class="bi bi-info-circle"></i> อัตรา: <?= number_format($NORMAL_RATE,2) ?> ฿/ชม.</div>
      </div>
    </div>
  </div>

  <!-- รายการบันทึก -->
  <div class="cardx p-3 mb-4" id="logsCard">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="m-0 font-weight-bold" style="color:var(--text-strong)"><i class="bi bi-journal-text"></i> บันทึกเวลาในช่วงที่เลือก</h6>
      <div class="d-flex align-items-center" style="gap:8px">
        <span class="small" style="color:var(--ink-muted)">เรียง “ล่าสุดก่อน”</span>
        <button id="toggleLogsBtn" type="button" class="btn btn-sm btn-outline-light" style="font-weight:800">
          <i class="bi bi-eye"></i> แสดงบันทึก
        </button>
      </div>
    </div>

    <div id="logsBody">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th style="width:140px"><i class="bi bi-calendar-event"></i> วันที่เข้า</th>
              <th style="width:110px"><i class="bi bi-alarm"></i> เวลาเข้า</th>
              <th style="width:140px"><i class="bi bi-calendar2-event"></i> วันที่ออก</th>
              <th style="width:110px"><i class="bi bi-alarm"></i> เวลาออก</th>
              <th style="width:130px"><i class="bi bi-tag"></i> ชนิดชั่วโมง</th>
              <th class="text-right" style="width:140px"><i class="bi bi-stopwatch"></i> ชั่วโมงรวม</th>
            </tr>
          </thead>
          <tbody>
            <?php if($logs->num_rows === 0): ?>
              <tr><td colspan="6" class="text-center" style="color:var(--ink-muted)"><i class="bi bi-emoji-neutral"></i> ไม่มีข้อมูลในช่วงที่เลือก</td></tr>
            <?php else: ?>
              <?php while($r = $logs->fetch_assoc()):
                $isOpen = (trim($r['time_out']) === '00:00:00');
                $s = strtotime($r['date_in'].' '.$r['time_in']);
                $e = $isOpen ? time() : strtotime($r['date_out'].' '.$r['time_out']);
                $dur = ($s && $e && $e >= $s) ? fmtHM($e - $s) : '-';
                $pill = $r['hour_type']==='fund' ? 'pill pill-fund' : 'pill pill-normal';
                $lbl  = $r['hour_type']==='fund' ? 'ชั่วโมงทุน' : 'ชั่วโมงปกติ';
              ?>
                <tr>
                  <td><?= h($r['date_in']) ?></td>
                  <td><?= h($r['time_in']) ?></td>
                  <td><?= h($r['date_out']) ?></td>
                  <td>
                    <?= h($r['time_out']) ?>
                    <?php if($isOpen): ?>
                      <span class="ml-1 badge badge-warning" title="กำลังทำงานอยู่" style="background:color-mix(in oklab, var(--warn), black 25%); color:#fff; border-radius:999px"><i class="bi bi-lightning"></i> live</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="<?= $pill ?>"><i class="bi bi-tags"></i> <?= h($lbl) ?><?= $isOpen?' (กำลังทำงาน)':'' ?></span></td>
                  <td class="text-right"><?= h($dur) ?></td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div> <!-- /#logsBody -->
  </div>   <!-- /#logsCard -->

</div>

<script>
// นาฬิกาปัจจุบัน (อัปเดตทุกวินาที)
(function(){
  const el = document.getElementById('nowClock');
  function pad(n){return (n<10?'0':'')+n}
  function tick(){
    const d = new Date();
    el.textContent = [pad(d.getHours()),pad(d.getMinutes()),pad(d.getSeconds())].join(':');
  }
  tick(); setInterval(tick, 1000);
})();
</script>

<script>
/* Toggle แสดง/ซ่อน บันทึก + จำสถานะไว้ใน localStorage
   ค่าเริ่มต้น (ไม่มี key) = ซ่อน */
(function(){
  const KEY = 'psu.userprofile.logs.hidden';
  const card = document.getElementById('logsCard');
  const body = document.getElementById('logsBody');
  const btn  = document.getElementById('toggleLogsBtn');

  if(!card || !body || !btn) return;

  function apply(hidden){
    card.classList.toggle('is-hidden', hidden);
    btn.innerHTML = hidden
      ? '<i class="bi bi-eye"></i> แสดงบันทึก'
      : '<i class="bi bi-eye-slash"></i> ซ่อนบันทึก';
    btn.setAttribute('aria-expanded', String(!hidden));
  }

  // ครั้งแรก (ไม่มี KEY) => ซ่อน
  let saved = null;
  try { saved = localStorage.getItem(KEY); } catch(e){}
  const initialHidden = (saved === null) ? true : (saved === '1');
  apply(initialHidden);

  btn.addEventListener('click', () => {
    const willHide = !card.classList.contains('is-hidden') ? true : false;
    apply(willHide);
    try { localStorage.setItem(KEY, willHide ? '1' : '0'); } catch(e){}
  });
})();
</script>
</body>
</html>

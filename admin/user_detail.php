<?php
// admin/user_detail.php — ดูรายละเอียดชั่วโมงของ “ผู้ใช้รายคน” + คิดเงินชั่วโมงปกติ
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

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
function fmtMoney(float $n): string { return number_format($n, 2); }

// ===== รับพารามิเตอร์ =====
$uid = (int)($_GET['id'] ?? 0);
if ($uid <= 0) { header('Location: users_list.php'); exit; }

// ช่วงวันที่เริ่มต้นค่าเริ่ม — เดือนนี้
$today  = (new DateTime('today'))->format('Y-m-d');
$month1 = (new DateTime('first day of this month'))->format('Y-m-d');

$start_date = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $month1;
$end_date   = isset($_GET['end'])   && $_GET['end']   !== '' ? $_GET['end']   : $today;

// ===== ดึงข้อมูลผู้ใช้ =====
$stmt = $conn->prepare("SELECT user_id, username, name, student_ID, role, status FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { header('Location: users_list.php'); exit; }

// ===== รวมเวลาตามชนิดชั่วโมง (เฉพาะแถวที่ปิดงานแล้ว) =====
$sqlSum = "
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
$stmt = $conn->prepare($sqlSum);
$stmt->bind_param('iss', $uid, $start_date, $end_date);
$stmt->execute();
$res = $stmt->get_result();

$sec_fund   = 0;
$sec_normal = 0;
while ($r = $res->fetch_assoc()) {
  if ($r['hour_type'] === 'fund')   $sec_fund   = (int)$r['sec_total'];
  if ($r['hour_type'] === 'normal') $sec_normal = (int)$r['sec_total'];
}
$stmt->close();

// ชั่วโมงแบบทศนิยม (คิดเงินสะดวก)
$hrs_normal_decimal = round($sec_normal / 3600, 2);
$hrs_fund_decimal   = round($sec_fund   / 3600, 2);

// จำนวนเงินสำหรับชั่วโมงปกติ
$wage_normal = $hrs_normal_decimal * $NORMAL_RATE;

// ===== รายการบันทึกแบบละเอียด =====
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
<title>รายละเอียดผู้ใช้ • ชั่วโมงทุน/ปกติ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --text-strong:#F4F7F8;
  --text-normal:#E6EBEE;
  --text-muted:#B9C2C9;

  --bg-grad1:#222831;     /* background */
  --bg-grad2:#393E46;

  --surface:#1C2228;      /* cards */
  --surface-2:#232A31;
  --surface-3:#2B323A;

  --ink:#F4F7F8;
  --ink-muted:#CFEAED;

  --brand-900:#EEEEEE;
  --brand-700:#BFC6CC;
  --brand-500:#00ADB5;    /* accent */
  --brand-400:#27C8CF;
  --brand-300:#73E2E6;

  --ok:#2ecc71; --danger:#e53935;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

/* ===== Page scaffold ===== */
html,body{height:100%}
body{
  background:
    radial-gradient(900px 360px at 110% -10%, rgba(39,200,207,.18), transparent 65%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{ max-width:1100px; margin:26px auto; padding:0 14px; }

/* ===== Topbar ===== */
.topbar{
  background:rgba(28,34,40,.78);
  border:1px solid rgba(255,255,255,.06);
  border-radius:14px; padding:12px 16px;
  box-shadow:var(--shadow-lg); backdrop-filter:blur(6px);
}
.topbar .title{ font-weight:900; color:var(--brand-900) }
.badge-chip{
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
  color:var(--brand-900); border:1px solid rgba(255,255,255,.12);
  border-radius:999px; padding:.25rem .6rem; font-weight:800
}
.topbar .btn{
  border-radius:12px; font-weight:800;
  border:1px solid rgba(255,255,255,.15); color:var(--text-normal);
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
}
.topbar .btn:hover{ filter:brightness(1.08) }

/* ===== Card ===== */
.cardx{
  background: linear-gradient(180deg,var(--surface),var(--surface-2));
  color:var(--ink);
  border:1px solid rgba(255,255,255,.06);
  border-radius:16px; box-shadow:var(--shadow);
}

/* ===== Forms ===== */
.form-inline label{ color:var(--brand-700); font-weight:700 }
.form-control{
  color:var(--text-strong); background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.10); border-radius:12px;
}
.form-control:focus{
  border-color: var(--brand-500);
  box-shadow:0 0 0 .2rem rgba(0,173,181,.25);
  background:#2F373F;
}
.btn-primary{
  background:linear-gradient(180deg, var(--brand-500), #07949B);
  border:0; border-radius:12px; font-weight:900; color:#061217;
  box-shadow:0 8px 22px rgba(0,173,181,.25);
}
.text-muted{ color:var(--text-muted)!important }

/* ===== KPI ===== */
.kpi{ display:grid; grid-template-columns: repeat(3,minmax(200px,1fr)); gap:12px; }
.tile{
  background:linear-gradient(180deg,#262e36,#212930);
  border:1px solid rgba(255,255,255,.08);
  border-radius:14px; padding:14px 16px;
}
.tile .n{ font-size:1.6rem; font-weight:900; color:var(--brand-300) }
.tile .l{ font-weight:800; color:var(--brand-700) }

/* ===== Table ===== */
.table thead th{
  background:#222a31; color:var(--brand-300);
  border-bottom:2px solid rgba(255,255,255,.08); font-weight:800;
}
.table td, .table th{ border-color: rgba(255,255,255,.06) !important; color:var(--text-normal) }
.table tbody tr:hover td{ background:#20262d; color:var(--text-strong) }

/* Pills */
.pill{display:inline-block;border-radius:999px;padding:.15rem .6rem;font-weight:800;border:1px solid rgba(255,255,255,.12)}
.pill-fund{background:rgba(46,204,113,.12); color:#7ee2a6; border-color:rgba(46,204,113,.35)}
.pill-normal{background:rgba(0,173,181,.12); color:#7fdfe3; border-color:rgba(0,173,181,.35)}

/* Small utilities */
.hr-soft{ border-top:1px dashed rgba(255,255,255,.12) }
a{ color:var(--brand-400) }
a:hover{ color:var(--brand-300) }
</style>
</head>
<body>
<div class="wrap">

  <!-- Top -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div>
      <div class="h5 m-0 title">รายละเอียดผู้ใช้ • ชั่วโมงทุน/ชั่วโมงปกติ</div>
      <small class="d-block mt-1">
        ชื่อ: <span class="badge-chip"><?= h($user['name']) ?></span>
        • รหัสนักศึกษา: <span class="badge-chip"><?= h($user['student_ID'] ?: '-') ?></span>
        • สถานะปัจจุบัน: <span class="badge-chip"><?= h($user['status'] ?: '-') ?></span>
      </small>
    </div>
    <div>
      <a href="users_list.php" class="btn btn-sm mr-2">← รายชื่อผู้ใช้</a>
      <a href="adminmenu.php" class="btn btn-sm">หน้า Admin</a>
    </div>
  </div>

  <!-- Filter -->
  <div class="cardx p-3 mb-3">
    <form class="form-inline" method="get">
      <input type="hidden" name="id" value="<?= (int)$uid ?>">
      <label class="mr-2">ช่วงวันที่</label>
      <input type="date" name="start" class="form-control mr-2" value="<?= h($start_date) ?>">
      <span class="mr-2">ถึง</span>
      <input type="date" name="end" class="form-control mr-2" value="<?= h($end_date) ?>">
      <button class="btn btn-primary">แสดง</button>
      <span class="ml-3 text-muted">* คิดเฉพาะรายการที่ “ปิดงานแล้ว”</span>
    </form>
  </div>

  <!-- KPI -->
  <div class="cardx p-3 mb-3">
    <div class="kpi">
      <div class="tile">
        <div class="l">ชั่วโมงทุน (รวม)</div>
        <div class="n"><?= fmtHM($sec_fund) ?> <small class="text-muted">(≈ <?= number_format($hrs_fund_decimal,2) ?> ชม.)</small></div>
      </div>
      <div class="tile">
        <div class="l">ชั่วโมงปกติ (รวม)</div>
        <div class="n"><?= fmtHM($sec_normal) ?> <small class="text-muted">(≈ <?= number_format($hrs_normal_decimal,2) ?> ชม.)</small></div>
      </div>
      <div class="tile">
        <div class="l">ค่าตอบแทนชั่วโมงปกติ</div>
        <div class="n"><?= fmtMoney($wage_normal) ?> ฿
          <div class="small text-muted mt-1">อัตรา <?= fmtMoney($NORMAL_RATE) ?> ฿/ชม.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- รายการบันทึก -->
  <div class="cardx p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="m-0" style="color:var(--brand-900); font-weight:800">บันทึกเวลาในช่วงที่เลือก</h6>
      <span class="text-muted small">แสดงผลล่าสุดก่อน</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:140px">วันที่เข้า</th>
            <th style="width:110px">เวลาเข้า</th>
            <th style="width:140px">วันที่ออก</th>
            <th style="width:110px">เวลาออก</th>
            <th style="width:130px">ชนิดชั่วโมง</th>
            <th class="text-right" style="width:140px">ชั่วโมงรวม</th>
          </tr>
        </thead>
        <tbody>
          <?php if($logs->num_rows === 0): ?>
            <tr><td colspan="6" class="text-center text-muted">ไม่มีข้อมูลในช่วงที่เลือก</td></tr>
          <?php else: ?>
            <?php while($r = $logs->fetch_assoc()):
              $dur = '-';
              if (trim($r['time_out']) !== '00:00:00') {
                $s = strtotime($r['date_in'].' '.$r['time_in']);
                $e = strtotime($r['date_out'].' '.$r['time_out']);
                if ($s && $e && $e >= $s) {
                  $sec = $e - $s; $dur = fmtHM($sec);
                }
              }
              $pill = $r['hour_type']==='fund' ? 'pill pill-fund' : 'pill pill-normal';
              $lbl  = $r['hour_type']==='fund' ? 'ชั่วโมงทุน' : 'ชั่วโมงปกติ';
            ?>
              <tr>
                <td><?= h($r['date_in']) ?></td>
                <td><?= h($r['time_in']) ?></td>
                <td><?= h($r['date_out']) ?></td>
                <td><?= h($r['time_out']) ?></td>
                <td><span class="<?= $pill ?>"><?= h($lbl) ?></span></td>
                <td class="text-right"><?= h($dur) ?></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>

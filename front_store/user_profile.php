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
// นับเพิ่มเฉพาะบรรทัดที่อยู่ในช่วงวันที่ที่เลือก (date_in ระหว่าง start~end)
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

$open_status = []; // เก็บรายการที่กำลังทำอยู่ เพื่อแสดงป้าย
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

// ชั่วโมงแบบทศนิยม (สองตำแหน่ง) สำหรับสรุป
$hrs_fund_closed     = round($sec_fund_closed/3600, 2);
$hrs_normal_closed   = round($sec_normal_closed/3600, 2);
$hrs_fund_live       = round($sec_fund_live/3600, 2);
$hrs_normal_live     = round($sec_normal_live/3600, 2);

// คิดค่าตอบแทนชั่วโมงปกติ (เวอร์ชัน “รวมถึงงานที่ค้างอยู่ถึงตอนนี้”)
$wage_normal_live   = $hrs_normal_live * $NORMAL_RATE;
// (ถ้าอยากโชว์เฉพาะ “ปิดงานแล้ว” ก็ใช้ $hrs_normal_closed * $NORMAL_RATE แยกอีกแถว)


// ===== รายการบันทึกละเอียด (ช่วงที่เลือก) =====
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
<style>
:root{
  --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-sky:#29ABE2; --psu-sritrang:#BBB4D8;
  --ok:#2e7d32; --muted:#6b7280;
}
body{ background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean)); color:#fff; font-family:"Segoe UI",Tahoma,Arial }
.wrap{ max-width:1100px; margin:26px auto; padding:0 14px; }
.topbar{
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  border-radius:14px; padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.cardx{ background:#fff; color:#0b2746; border:1px solid #dbe6ff; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.22) }
.badge-chip{ background:#eaf4ff; color:#0D4071; border:1px solid #cfe2ff; border-radius:999px; padding:.25rem .6rem; font-weight:800 }
.kpi{ display:grid; grid-template-columns: repeat(3,minmax(200px,1fr)); gap:12px; }
.tile{ background:#f7fbff; border:1px solid #e3eeff; border-radius:14px; padding:14px 16px; }
.tile .n{ font-size:1.6rem; font-weight:900; color:#0D4071 }
.tile .l{ font-weight:800; color:#1b4b83; opacity:.9 }
.table thead th{ background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; }
.table td,.table th{ border-color:#e7eefc!important }
.pill{display:inline-block;border-radius:999px;padding:.15rem .5rem;font-weight:800}
.pill-fund{background:#eaf7ea;color:#1b5e20;border:1px solid #cfe9cf}
.pill-normal{background:#e9f5ff;color:#0D4071;border:1px solid #cfe2ff}
.alert-live{background:#fff8e1;color:#5d4037;border:1px solid #ffe082;border-radius:12px}
</style>
</head>
<body>
<div class="wrap">

  <!-- Top -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div>
      <div class="h5 m-0 font-weight-bold">โปรไฟล์ของฉัน • ชั่วโมงทำงาน</div>
      <small class="text-light">
        ชื่อ: <span class="badge-chip"><?= h($user['name'] ?: $user['username']) ?></span>
        • รหัสนักศึกษา: <span class="badge-chip"><?= h($user['student_ID'] ?: '-') ?></span>
        • สถานะ: <span class="badge-chip"><?= h($user['status'] ?: '-') ?></span>
      </small>
    </div>
    <div>
      <a href="front_store.php" class="btn btn-sm btn-outline-light">← กลับหน้าเมนู</a>
    </div>
  </div>

  <!-- ถ้ากำลังปฏิบัติงาน -->
  <?php if (!empty($open_status)): ?>
    <div class="cardx p-3 mb-3 alert-live">
      <div class="font-weight-bold mb-1">กำลังปฏิบัติงานอยู่ตอนนี้</div>
      <ul class="mb-0 pl-3">
        <?php foreach($open_status as $o): ?>
          <li>
            ชนิด: <strong><?= $o['type']==='fund'?'ชั่วโมงทุน':'ชั่วโมงปกติ' ?></strong>
            &nbsp;เริ่มเมื่อ: <strong><?= h($o['since']) ?></strong>
            &nbsp;เวลาที่นับถึงตอนนี้: <strong><?= h(fmtHM((int)$o['elapsed'])) ?></strong>
          </li>
        <?php endforeach; ?>
      </ul>
      <small class="text-muted d-block mt-1">* เวลาด้านล่าง “รวมถึง” งานที่กำลังทำอยู่แล้ว ณ ขณะนี้</small>
    </div>
  <?php endif; ?>

  <!-- Filter -->
  <div class="cardx p-3 mb-3">
    <form class="form-inline" method="get">
      <label class="mr-2">ช่วงวันที่</label>
      <input type="date" name="start" class="form-control mr-2" value="<?= h($start_date) ?>">
      <span class="mr-2">ถึง</span>
      <input type="date" name="end" class="form-control mr-2" value="<?= h($end_date) ?>">
      <button class="btn btn-primary">แสดง</button>
      <span class="ml-3 text-muted">* นับงานที่ปิดแล้ว + งานที่กำลังทำ</span>
    </form>
  </div>

  <!-- KPI (Live = รวมจนถึงตอนนี้) -->
   <div class="cardx p-3 mb-3">
    <div class="kpi">
      <div class="tile">
        <div class="l">ชั่วโมงทุน (รวมถึงงานที่กำลังทำ)</div>
        <div class="n"><?= fmtHM($sec_fund_live) ?></div>
        <div class="small text-muted mt-1">ปิดงานแล้ว: <?= fmtHM($sec_fund_closed) ?> (≈ <?= number_format($hrs_fund_closed,2) ?> ชม.)</div>
      </div>
      <div class="tile">
        <div class="l">ชั่วโมงปกติ (รวมถึงงานที่กำลังทำ)</div>
        <div class="n"><?= fmtHM($sec_normal_live) ?></div>
        <div class="small text-muted mt-1">ปิดงานแล้ว: <?= fmtHM($sec_normal_closed) ?> (≈ <?= number_format($hrs_normal_closed,2) ?> ชม.)</div>
      </div>
      <div class="tile">
        <div class="l">ค่าตอบแทนชั่วโมงปกติ</div>
        <div class="n"><?= money2($wage_normal_live) ?> ฿</div>
      </div>
    </div>
  </div>

  <!-- รายการบันทึก -->
  <div class="cardx p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h6 class="m-0 font-weight-bold">บันทึกเวลาในช่วงที่เลือก</h6>
      <span class="text-muted small">เรียง “ล่าสุดก่อน”</span>
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
              // H:mm ของแต่ละบรรทัด (ถ้ายังไม่ออกงาน แสดงเวลาถึงตอนนี้)
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
                <td><?= h($r['time_out']) ?></td>
                <td><span class="<?= $pill ?>"><?= h($lbl) ?><?= $isOpen?' (กำลังทำงาน)':'' ?></span></td>
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

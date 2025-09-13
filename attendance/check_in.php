<?php
// SelectRole/check_in.php — ลงเวลาทำงาน (Attendance)
// + จำกัดช่วงเช็คอิน (เวลาไทยตรงกัน PHP/MySQL)
// + Auto checkout หลังเลิกงาน 5 นาที
// + Toggle ซ่อน/แสดง "บันทึกล่าสุด"
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ==== เวลาไทยให้ตรงทั้ง PHP/MySQL ==== */
date_default_timezone_set('Asia/Bangkok');
$conn->query("SET time_zone = '+07:00'");

$user_id  = (int)$_SESSION['uid'];
$username = (string)($_SESSION['username'] ?? '');
$name     = (string)($_SESSION['name'] ?? '');

/* ---------------- Config/Helpers ---------------- */
const AUTO_CO_GRACE_MIN = 5; // ปิดงานอัตโนมัติหลังเลิกงานกี่นาที

/** นิยามรอบงาน (ช่วงเช็คอิน + เวลาเลิกงาน) */
function work_windows(): array {
  // label ใช้เพื่อแสดงผล
  return [
    ['start'=>'09:00','end'=>'09:30','end_work'=>'12:00','label'=>'เช้า (09:00–09:30 • เลิก 12:00)'],
    ['start'=>'13:00','end'=>'13:30','end_work'=>'17:00','label'=>'บ่าย (13:00–13:30 • เลิก 17:00)'],
  ];
}
/** ช่วงที่อนุญาตเช็คอิน */
function allowed_windows(): array {
  return array_map(fn($x)=>['start'=>$x['start'],'end'=>$x['end'],'label'=>preg_replace('/ • เลิก .+$/','',$x['label'])], work_windows());
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** ตอนนี้อยู่ในช่วงเช็คอินหรือไม่ */
function is_within_windows(DateTime $now): bool {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $e = DateTime::createFromFormat('H:i', $w['end'],   new DateTimeZone('Asia/Bangkok'));
    foreach ([$s,$e] as $t) { $t->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d')); }
    if ($now >= $s && $now <= $e) return true;
  }
  return false;
}
/** ป้ายช่วงปัจจุบัน */
function current_window_label(DateTime $now): string {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $e = DateTime::createFromFormat('H:i', $w['end'],   new DateTimeZone('Asia/Bangkok'));
    foreach ([$s,$e] as $t) { $t->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d')); }
    if ($now >= $s && $now <= $e) return 'ขณะนี้อยู่ในช่วง '.$w['label'];
  }
  return 'ขณะนี้อยู่นอกช่วงเวลาเช็คอิน';
}
/** สตริงอธิบายช่วงทั้งหมด */
function windows_text(): string {
  $labels = array_map(fn($x)=>$x['label'], allowed_windows());
  return 'เช็คอินได้เฉพาะ: '.implode(' และ ', $labels);
}
/** ข้อความช่วงถัดไปของวันนี้ (ถ้ามี) */
function next_window_message(DateTime $now): string {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $s->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
    if ($now < $s) return 'ช่วงถัดไป: '.$w['label'];
  }
  return '';
}
/** หาว่าการเช็คอินครั้งนี้อยู่รอบไหน และเวลาเลิกงานคือกี่โมงของวันเดียวกัน */
function resolve_end_work(string $date_in, string $time_in): ?DateTime {
  $checkin = DateTime::createFromFormat('Y-m-d H:i:s', $date_in.' '.$time_in, new DateTimeZone('Asia/Bangkok'));
  if (!$checkin) return null;
  foreach (work_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $e = DateTime::createFromFormat('H:i', $w['end'],   new DateTimeZone('Asia/Bangkok'));
    foreach ([$s,$e] as $t) { $t->setDate((int)$checkin->format('Y'), (int)$checkin->format('m'), (int)$checkin->format('d')); }
    if ($checkin >= $s && $checkin <= $e) {
      $endWork = DateTime::createFromFormat('H:i', $w['end_work'], new DateTimeZone('Asia/Bangkok'));
      $endWork->setDate((int)$checkin->format('Y'), (int)$checkin->format('m'), (int)$checkin->format('d'));
      return $endWork;
    }
  }
  // ถ้าเช็คอินนอกนิยามข้างบน ให้ถือว่าเลิกงานวันนี้ 23:59
  $fallback = DateTime::createFromFormat('Y-m-d H:i', $date_in.' 23:59', new DateTimeZone('Asia/Bangkok'));
  return $fallback ?: null;
}
/** ระยะเวลาแบบ H:mm (รวมวัน); ยังไม่ปิดงานคืน "-" */
function calc_duration($date_in, $time_in, $date_out, $time_out): string {
  if (trim((string)$time_out) === '00:00:00') return '-';
  try{
    $start = new DateTime("$date_in $time_in", new DateTimeZone('Asia/Bangkok'));
    $end   = new DateTime("$date_out $time_out", new DateTimeZone('Asia/Bangkok'));
    if ($end < $start) return '-';
    $diff = $start->diff($end);
    $hours = $diff->days * 24 + $diff->h;
    $mins  = str_pad((string)$diff->i, 2, '0', STR_PAD_LEFT);
    return $hours.':'.$mins.' ชม.';
  }catch(Exception $e){ return '-'; }
}

/* ==== เวลาเป๊ะ ๆ ==== */
$nowPhp = new DateTime('now', new DateTimeZone('Asia/Bangkok'));
$rowNow = $conn->query("SELECT NOW() AS now_db")->fetch_assoc();
$nowDb  = $rowNow ? $rowNow['now_db'] : '';

$in_window       = is_within_windows($nowPhp);
$window_label    = current_window_label($nowPhp);
$windows_all_txt = windows_text();
$next_window     = next_window_message($nowPhp);

/* ==== สถานะชั่วโมงจาก users ==== */
$user_status_current = 'ชั่วโมงปกติ';
$stmt = $conn->prepare("SELECT status FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
  $user_status_current = trim((string)$row['status']) ?: 'ชั่วโมงปกติ';
}
$stmt->close();

/* ==== หา record เปิดอยู่ล่าสุด ==== */
$stmt = $conn->prepare("
  SELECT attendance_id, user_id, date_in, time_in, date_out, time_out, hour_type
  FROM attendance
  WHERE user_id = ? AND time_out = '00:00:00'
  ORDER BY attendance_id DESC
  LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$open = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ==== Auto checkout ถ้าถึงเวลาเลิกงาน + 5 นาที ==== */
if ($open) {
  $endWork = resolve_end_work($open['date_in'], $open['time_in']);  // เวลาเลิกงานตามรอบ
  if ($endWork) {
    $cutoff = (clone $endWork)->modify('+'.AUTO_CO_GRACE_MIN.' minutes'); // เลยเลิกงานกี่นาทีถึงจะปิดเอง
    if ($nowPhp >= $cutoff) {
      $aid = (int)$open['attendance_id'];
      // ปิดงานให้ ณ เวลา "เลิกงาน" (ไม่ใช่ตอน +5 นาที)
      $date_out = $endWork->format('Y-m-d');
      $time_out = $endWork->format('H:i:s');
      $stmt = $conn->prepare("
        UPDATE attendance
        SET date_out = ?, time_out = ?
        WHERE attendance_id = ? AND time_out = '00:00:00'
      ");
      $stmt->bind_param('ssi', $date_out, $time_out, $aid);
      $stmt->execute();
      $stmt->close();
      // รีเฟรชสถานะเปิดงาน
      $open = null;
    }
  }
}

/* ==== จัดการ POST (checkin / checkout) ==== */
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  if ($action === 'checkin') {
    if ($open) {
      $err = 'คุณมีการเข้างานที่ยังไม่ปิดอยู่แล้ว (ห้ามเช็คอินซ้ำ)';
    } elseif (!$in_window) {
      $err = 'อยู่นอกช่วงเวลาเช็คอิน ('. $windows_all_txt .')';
    } else {
      $hour_type = ($user_status_current === 'ชั่วโมงทุน') ? 'fund' : 'normal';
      $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, date_in, time_in, date_out, time_out, hour_type)
        VALUES (?, CURDATE(), CURTIME(), CURDATE(), '00:00:00', ?)
      ");
      $stmt->bind_param('is', $user_id, $hour_type);
      if ($stmt->execute()) $msg = 'เช็คอินเรียบร้อย';
      else $err = 'เช็คอินไม่สำเร็จ';
      $stmt->close();
      header("Location: check_in.php?msg=1"); exit;
    }
  }

  if ($action === 'checkout') {
    if (!$open) {
      $err = 'ยังไม่ได้เช็คอิน — ไม่สามารถเช็คเอาท์ได้';
    } else {
      $aid = (int)$open['attendance_id'];
      $stmt = $conn->prepare("
        UPDATE attendance
        SET date_out = CURDATE(), time_out = CURTIME()
        WHERE attendance_id = ? AND time_out = '00:00:00'
      ");
      $stmt->bind_param('i', $aid);
      if ($stmt->execute() && $stmt->affected_rows > 0) $msg = 'เช็คเอาท์เรียบร้อย';
      else $err = 'เช็คเอาท์ไม่สำเร็จ หรือปิดไปแล้ว';
      $stmt->close();
      header("Location: check_in.php?msg=2"); exit;
    }
  }
}

if (isset($_GET['msg'])) {
  if ($_GET['msg'] === '1') $msg = 'เช็คอินเรียบร้อย';
  if ($_GET['msg'] === '2') $msg = 'เช็คเอาท์เรียบร้อย';
}

/* ==== Logs ล่าสุด ==== */
$logs = [];
$stmt = $conn->prepare("
  SELECT attendance_id, date_in, time_in, date_out, time_out, hour_type
  FROM attendance
  WHERE user_id = ?
  ORDER BY attendance_id DESC
  LIMIT 30
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $logs[] = $r;
$stmt->close();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ลงเวลาทำงาน • Attendance</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2;  --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ok:#2e7d32; --warn:#f0ad4e; --bad:#d9534f;
}
body{ background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean)); color:#fff; font-family:"Segoe UI",Tahoma,sans-serif; }
.wrap{max-width:980px;margin:28px auto;padding:0 14px}
.headbar{ background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  border-radius:14px; padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.18); }
.cardx{ background:rgba(255,255,255,.09); border:1px solid var(--psu-sritrang);
  border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.22); }
.stat{ background:#fff; color:#0D4071; border-radius:14px; padding:16px;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; }
.badge-open{background:var(--warn); color:#212529; font-weight:800}
.badge-close{background:var(--ok); color:#fff; font-weight:800}
.btn-ci{background:var(--psu-andaman); border-color:#063d63; font-weight:800}
.btn-ci:hover{background:var(--psu-sky); color:#063d63}
.btn-co{background:#e85f5f; border-color:#a73737; font-weight:800}
.table-logs{background:#fff; color:#0b2746; border-radius:12px; overflow:hidden}
.table-logs thead th{ background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; font-weight:800; }
.table-logs td,.table-logs th{ border-color:#e7eefc!important; }
.hint{ background:#eaf4ff; color:#0D4071; border:1px solid #bcd6f3; border-radius:12px;
  padding:12px 14px; margin-bottom:12px; font-weight:700; }
.debug{ background:#fff4e5; color:#663c00; border:1px dashed #f0ad4e; border-radius:10px; padding:8px 12px; font-size:.9rem; }
.badge-ht{ display:inline-block; padding:.2rem .5rem; border-radius:999px; font-weight:800; }
.badge-ht-normal{ background:#e9f5ff; color:#0D4071; border:1px solid #cfe2ff; }
.badge-ht-fund{ background:#eaf7ea; color:#1b5e20; border:1px solid #cfe9cf; }
.toggle-btn{border-radius:999px;font-weight:800}
</style>
</head>
<body>
<div class="wrap">
  <div class="headbar d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="h5 m-0 font-weight-bold">ลงเวลาทำงาน • Attendance</div>
      <small class="text-light">
        ผู้ใช้: <?= h($username ?: $name) ?>
        • สถานะชั่วโมงปัจจุบัน:
        <span class="<?= $user_status_current==='ชั่วโมงทุน'?'badge-ht badge-ht-fund':'badge-ht badge-ht-normal' ?>">
          <?= h($user_status_current) ?>
        </span>
      </small>
    </div>
    <div>
      <a href="../SelectRole/role.php" class="btn btn-sm btn-outline-light">ย้อนกลับ</a>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <div class="hint">
    <?= h($windows_all_txt) ?>.
    <br>
    <small><?= h($window_label) ?> <?= $next_window ? '• '.h($next_window) : '' ?></small>
  </div>

  <div class="debug mb-3">
    เวลา PHP: <strong><?= h($nowPhp->format('Y-m-d H:i:s')) ?></strong> •
    เวลา MySQL: <strong><?= h($nowDb) ?></strong>
  </div>

  <?php if($msg): ?>
    <div class="alert" style="background:#2e7d32;color:#fff;border:none;border-radius:10px"><?= h($msg) ?></div>
  <?php endif; ?>
  <?php if($err): ?>
    <div class="alert" style="background:#d9534f;color:#fff;border:none;border-radius:10px"><?= h($err) ?></div>
  <?php endif; ?>

  <!-- สถานะ -->
  <div class="cardx p-3 mb-3">
    <div class="stat">
      <div>
        <?php if($open): ?>
          <div class="h5 m-0">
            สถานะ: <span class="badge badge-open p-2">กำลังเข้างาน</span>
            <span class="ml-2 <?= $open['hour_type']==='fund'?'badge-ht badge-ht-fund':'badge-ht badge-ht-normal' ?>">
              <?= $open['hour_type']==='fund'?'ชั่วโมงทุน':'ชั่วโมงปกติ' ?>
            </span>
          </div>
          <div class="mt-2">
            เข้างานเมื่อ: <strong><?= h($open['date_in'].' '.$open['time_in']) ?></strong>
          </div>
        <?php else: ?>
          <div class="h5 m-0">สถานะ: <span class="badge badge-close p-2">ว่าง (ไม่ได้เข้างาน)</span></div>
          <div class="mt-1">
            <small>สถานะชั่วโมงปัจจุบันของคุณ: 
              <span class="<?= $user_status_current==='ชั่วโมงทุน'?'badge-ht badge-ht-fund':'badge-ht badge-ht-normal' ?>">
                <?= h($user_status_current) ?>
              </span>
            </small>
            <br><small><?= h($window_label) ?></small>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <form method="post" class="d-inline" onsubmit="return confirmCheckin();">
          <input type="hidden" name="action" value="checkin">
          <button type="submit" class="btn btn-ci"
            <?= ($open || !$in_window) ? 'disabled' : '' ?>
            title="<?= !$in_window ? h($windows_all_txt) : 'เช็คอินตอนนี้' ?>">
            เข้างาน
          </button>
        </form>
        <form method="post" class="d-inline ml-2">
          <input type="hidden" name="action" value="checkout">
          <button type="submit" class="btn btn-co" <?= $open ? '' : 'disabled' ?>>ออกงาน</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Logs + Toggle -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="h5 m-0">บันทึกล่าสุด</div>
    <button id="toggleLogs" class="btn btn-light btn-sm toggle-btn">ซ่อนบันทึกล่าสุด</button>
  </div>

  <div id="logsBox" class="cardx p-3">
    <div class="table-responsive">
      <table class="table table-sm table-logs mb-0">
        <thead>
          <tr>
            <th style="width:120px">วันที่เข้างาน</th>
            <th style="width:110px">เวลาเข้า</th>
            <th style="width:120px">วันที่ออกงาน</th>
            <th style="width:110px">เวลาออก</th>
            <th style="width:120px">ชั่วโมงรวม</th>
            <th style="width:120px">ชนิดชั่วโมง</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($logs)): ?>
            <tr><td colspan="7" class="text-center text-muted">ยังไม่มีบันทึก</td></tr>
          <?php else: foreach($logs as $r):
            $dur = calc_duration($r['date_in'],$r['time_in'],$r['date_out'],$r['time_out']);
            $is_open = (trim($r['time_out'])==='00:00:00');
            $ht_lbl  = ($r['hour_type']==='fund'?'ชั่วโมงทุน':'ชั่วโมงปกติ');
          ?>
            <tr>
              <td><?= h($r['date_in']) ?></td>
              <td><?= h($r['time_in']) ?></td>
              <td><?= h($r['date_out']) ?></td>
              <td><?= h($r['time_out']) ?></td>
              <td><?= h($dur) ?></td>
              <td>
                <span class="badge-ht <?= $r['hour_type']==='fund'?'badge-ht-fund':'badge-ht-normal' ?>">
                  <?= h($ht_lbl) ?>
                </span>
              </td>
              <td>
                <?php if($is_open): ?>
                  <span class="badge badge-warning">กำลังเข้างาน</span>
                <?php else: ?>
                  <span class="badge badge-success">ปิดงานแล้ว</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function confirmCheckin(){
  var btn = document.querySelector('.btn-ci');
  if (btn && btn.hasAttribute('disabled')) {
    alert('เช็คอินได้เฉพาะ <?= h($windows_all_txt) ?>');
    return false;
  }
  return true;
}

// Toggle logs show/hide + remember
(function(){
  const KEY = 'psu.attn.logs.hidden';
  const btn = document.getElementById('toggleLogs');
  const box = document.getElementById('logsBox');

  function applyHidden(hide){
    box.style.display = hide ? 'none' : '';
    btn.textContent = hide ? 'แสดงบันทึกล่าสุด' : 'ซ่อนบันทึกล่าสุด';
  }
  try {
    const saved = localStorage.getItem(KEY);
    applyHidden(saved === '1');
  } catch(e){}

  btn.addEventListener('click', ()=>{
    const hide = box.style.display !== 'none' ? true : false;
    applyHidden(hide);
    try { localStorage.setItem(KEY, hide ? '1':'0'); } catch(e){}
  });
})();
</script>
</body>
</html>

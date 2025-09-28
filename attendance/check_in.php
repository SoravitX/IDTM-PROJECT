<?php
// SelectRole/check_in.php — ลงเวลาทำงาน (Attendance)
// + จำกัดช่วงเช็คอิน (เวลาไทยตรงกัน PHP/MySQL)
// + Auto checkout หลังเลิกงาน 5 นาที
// + Toggle ซ่อน/แสดง "บันทึกล่าสุด"
// + UI Theme: Teal-Graphite (เข้ม อ่านง่าย)
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
  return [
    ['start'=>'09:00','end'=>'09:30','end_work'=>'12:00','label'=>'เช้า (09:00–09:30 • เลิก 12:00)'],
    ['start'=>'13:00','end'=>'13:30','end_work'=>'17:00','label'=>'บ่าย (13:00–13:30 • เลิก 17:00)'],
  ];
}
function allowed_windows(): array {
  return array_map(fn($x)=>['start'=>$x['start'],'end'=>$x['end'],'label'=>preg_replace('/ • เลิก .+$/','',$x['label'])], work_windows());
}
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function is_within_windows(DateTime $now): bool {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $e = DateTime::createFromFormat('H:i', $w['end'],   new DateTimeZone('Asia/Bangkok'));
    foreach ([$s,$e] as $t) { $t->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d')); }
    if ($now >= $s && $now <= $e) return true;
  }
  return false;
}
function current_window_label(DateTime $now): string {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $e = DateTime::createFromFormat('H:i', $w['end'],   new DateTimeZone('Asia/Bangkok'));
    foreach ([$s,$e] as $t) { $t->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d')); }
    if ($now >= $s && $now <= $e) return 'ขณะนี้อยู่ในช่วง '.$w['label'];
  }
  return 'ขณะนี้อยู่นอกช่วงเวลาเช็คอิน';
}
function windows_text(): string {
  $labels = array_map(fn($x)=>$x['label'], allowed_windows());
  return 'เช็คอินได้เฉพาะ: '.implode(' และ ', $labels);
}
function next_window_message(DateTime $now): string {
  foreach (allowed_windows() as $w) {
    $s = DateTime::createFromFormat('H:i', $w['start'], new DateTimeZone('Asia/Bangkok'));
    $s->setDate((int)$now->format('Y'), (int)$now->format('m'), (int)$now->format('d'));
    if ($now < $s) return 'ช่วงถัดไป: '.$w['label'];
  }
  return '';
}
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
  $fallback = DateTime::createFromFormat('Y-m-d H:i', $date_in.' 23:59', new DateTimeZone('Asia/Bangkok'));
  return $fallback ?: null;
}
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

/* ==== Auto checkout ==== */
if ($open) {
  $endWork = resolve_end_work($open['date_in'], $open['time_in']);
  if ($endWork) {
    $cutoff = (clone $endWork)->modify('+'.AUTO_CO_GRACE_MIN.' minutes');
    if ($nowPhp >= $cutoff) {
      $aid = (int)$open['attendance_id'];
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
      $open = null;
    }
  }
}

/* ==== POST ==== */
$msg = ''; $err = '';
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
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
/* ===== Teal-Graphite Theme Tokens ===== */
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

  --ring:#73E2E6;
  --radius:14px;
}

html,body{height:100%}
body{
  margin:0; color:var(--ink); font-family:"Segoe UI",Tahoma,Arial,sans-serif;
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
}
.wrap{max-width:980px;margin:28px auto;padding:0 14px}

/* ===== Top header ===== */
.headbar{
  background:color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
  border-radius:var(--radius);
  padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.35);
}
.headbar .h5{letter-spacing:.2px}
.headbar .btn{border-radius:12px;font-weight:800}

/* ===== Info chip ===== */
.hint{
  background:color-mix(in oklab, var(--surface-2), white 6%);
  color:var(--text-normal);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:var(--radius); padding:12px 14px; margin-bottom:12px; font-weight:700;
  box-shadow:0 8px 18px rgba(0,0,0,.25);
}

/* ===== Server time ===== */
.debug{
  background:#fff4e5; color:#663c00; border:1px dashed #f0ad4e;
  border-radius:10px; padding:8px 12px; font-size:.9rem;
}

/* ===== Card ===== */
.cardx{
  background:color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:16px; box-shadow:var(--shadow);
}

/* ===== Status bar ===== */
.stat{
  background:var(--surface-2);
  color:var(--ink);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:16px; padding:16px;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
  box-shadow:0 8px 24px rgba(0,0,0,.25);
}
.badge-open{
  background:#FFE08A; color:#2b1a00; font-weight:900; border-radius:999px; padding:.35rem .6rem
}
.badge-close{
  background: color-mix(in oklab, var(--ok), black 10%);
  color:#041c10; font-weight:900; border-radius:999px; padding:.35rem .6rem;
}

.badge-ht{ display:inline-block; padding:.2rem .55rem; border-radius:999px; font-weight:900; }
.badge-ht-normal{
  background: color-mix(in oklab, var(--surface-3), white 8%);
  color: var(--brand-300);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
}
.badge-ht-fund{
  background: color-mix(in oklab, var(--ok), black 20%);
  color:#0b2a17;
  border:1px solid color-mix(in oklab, var(--ok), black 35%);
}

/* ===== Buttons ===== */
.btn-ci{
  background:linear-gradient(180deg,var(--brand-500),var(--brand-400));
  border:0; color:#062b33; font-weight:900; border-radius:12px; padding:.55rem 1.1rem;
  box-shadow:var(--shadow);
}
.btn-ci:hover{ filter:brightness(1.05) }
.btn-ci:disabled{ opacity:.6; cursor:not-allowed; }

.btn-co{
  background:linear-gradient(180deg,#ff6b6b,#e94444);
  border:0; color:#2a0202; font-weight:900; border-radius:12px; padding:.55rem 1.1rem;
  box-shadow:var(--shadow);
}
.btn-co:disabled{ opacity:.5; cursor:not-allowed; }

/* ปุ่มออกจากระบบในหัวให้แดง */
.headbar a.btn.btn-sm:last-child{
  background: linear-gradient(180deg, #ff6b6b, #e94444);
  border:1px solid #c22f2f; color:#fff;
}

/* ===== Logs table ===== */
.table-logs{
  background:var(--surface);
  color:var(--ink);
  border-radius:12px; overflow:hidden; table-layout:auto
}
.table-logs thead th{
  background:var(--surface-2); color:var(--text-strong);
  border-bottom:2px solid color-mix(in oklab, var(--brand-400), white 35%); font-weight:900;
}
.table-logs td,.table-logs th{ border-color: color-mix(in oklab, var(--brand-700), white 65%) !important; vertical-align:middle!important }
.table-logs tbody tr:hover td{ background:color-mix(in oklab, var(--surface-3), white 8%); }

/* ===== Toggle button ===== */
.toggle-btn{
  border-radius:999px;font-weight:800;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  color:var(--brand-900);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
}
.toggle-btn:hover{ filter:brightness(1.03) }

/* a11y + details */
:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#3a4752}
*::-webkit-scrollbar-track{background:#151a20}

/* ลมหายใจเล็ก ๆ เมื่ออยู่ในช่วงเช็คอิน */
.pulse{ animation:pulse 1.3s ease-in-out infinite }
@keyframes pulse{ 0%{opacity:.7} 50%{opacity:1} 100%{opacity:.7} }
</style>
</head>
<body>
<div class="wrap">

  <!-- Header -->
  <div class="headbar d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="h5 m-0 font-weight-bold"><i class="bi bi-clock-history"></i> ลงเวลาทำงาน • Attendance</div>
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
      <a href="../logout.php" class="btn btn-sm"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <!-- Windows info -->
  <div class="hint">
    <?= h($windows_all_txt) ?>.
    <br>
    <small class="<?= $in_window ? 'pulse':'' ?>"><?= h($window_label) ?> <?= $next_window ? '• '.h($next_window) : '' ?></small>
  </div>

  <div class="debug mb-3">
    เวลา PHP: <strong><?= h($nowPhp->format('Y-m-d H:i:s')) ?></strong> •
    เวลา MySQL: <strong><?= h($nowDb) ?></strong>
  </div>

  <?php if($msg): ?>
    <div class="alert" style="background:var(--ok);color:#fff;border:none;border-radius:10px"><i class="bi bi-check2-circle"></i> <?= h($msg) ?></div>
  <?php endif; ?>
  <?php if($err): ?>
    <div class="alert" style="background:var(--danger);color:#fff;border:none;border-radius:10px"><i class="bi bi-x-octagon"></i> <?= h($err) ?></div>
  <?php endif; ?>

  <!-- Status -->
  <div class="cardx p-3 mb-3">
    <div class="stat">
      <div>
        <?php if($open): ?>
          <div class="h5 m-0">
            สถานะ: <span class="badge-open">กำลังเข้างาน</span>
            <span class="ml-2 <?= $open['hour_type']==='fund'?'badge-ht badge-ht-fund':'badge-ht badge-ht-normal' ?>">
              <?= $open['hour_type']==='fund'?'ชั่วโมงทุน':'ชั่วโมงปกติ' ?>
            </span>
          </div>
          <div class="mt-2" style="color:var(--ink-muted)">
            เข้างานเมื่อ: <strong><?= h($open['date_in'].' '.$open['time_in']) ?></strong>
          </div>
        <?php else: ?>
          <div class="h5 m-0">สถานะ: <span class="badge-close">ว่าง (ไม่ได้เข้างาน)</span></div>
          <div class="mt-1">
            <small>สถานะชั่วโมงปัจจุบันของคุณ:
              <span class="<?= $user_status_current==='ชั่วโมงทุน'?'badge-ht badge-ht-fund':'badge-ht badge-ht-normal' ?>">
                <?= h($user_status_current) ?>
              </span>
            </small>
            <br><small class="<?= $in_window ? 'pulse':'' ?>"><?= h($window_label) ?></small>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <form method="post" class="d-inline" onsubmit="return confirmCheckin();">
          <input type="hidden" name="action" value="checkin">
          <button type="submit" class="btn btn-ci"
            <?= ($open || !$in_window) ? 'disabled' : '' ?>
            title="<?= !$in_window ? h($windows_all_txt) : 'เช็คอินตอนนี้' ?>">
            <i class="bi bi-door-open"></i> เข้างาน
          </button>
        </form>
        <form method="post" class="d-inline ml-2">
          <input type="hidden" name="action" value="checkout">
          <button type="submit" class="btn btn-co" <?= $open ? '' : 'disabled' ?>><i class="bi bi-door-closed"></i> ออกงาน</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Logs + Toggle -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="h5 m-0"><i class="bi bi-journal-text"></i> บันทึกล่าสุด</div>
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
            <th style="width:140px">ชนิดชั่วโมง</th>
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
                  <span class="badge badge-warning" style="font-weight:800">กำลังเข้างาน</span>
                <?php else: ?>
                  <span class="badge badge-success" style="font-weight:800">ปิดงานแล้ว</span>
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
  try { applyHidden(localStorage.getItem(KEY) === '1'); } catch(e){}

  btn.addEventListener('click', ()=>{
    const hide = box.style.display !== 'none';
    applyHidden(hide);
    try { localStorage.setItem(KEY, hide ? '1':'0'); } catch(e){}
  });
})();
</script>
</body>
</html>

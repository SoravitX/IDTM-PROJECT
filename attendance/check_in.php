<?php
// SelectRole/check_in.php — หน้า "ลงเวลาทำงาน" (Attendance)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$user_id = (int)$_SESSION['uid'];
$username = (string)($_SESSION['username'] ?? '');
$name = (string)($_SESSION['name'] ?? '');

// ---------- Helpers ----------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function thai_datetime($dt){ return $dt ? date('Y-m-d H:i:s', strtotime($dt)) : ''; }
function calc_duration($date_in, $time_in, $date_out, $time_out): string {
  // ถ้า time_out = 00:00:00 ถือว่ายังไม่ปิดงาน → แสดง '-'
  if (trim($time_out) === '00:00:00') return '-';
  try{
    $start = new DateTime($date_in.' '.$time_in);
    $end   = new DateTime($date_out.' '.$time_out);
    if ($end < $start) return '-';
    $diff = $start->diff($end);
    // รูปแบบ H:mm (นับรวมวัน)
    $hours = $diff->days * 24 + $diff->h;
    $mins  = str_pad((string)$diff->i, 2, '0', STR_PAD_LEFT);
    return $hours.':'.$mins.' ชม.';
  }catch(Exception $e){ return '-'; }
}

// ---------- หา record ล่าสุดที่ "ยังไม่ปิดงาน" ----------
$open = null;
$stmt = $conn->prepare("
  SELECT attendance_id, user_id, date_in, time_in, date_out, time_out
  FROM attendance
  WHERE user_id = ? AND time_out = '00:00:00'
  ORDER BY attendance_id DESC
  LIMIT 1
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$open = $res->fetch_assoc();
$stmt->close();

// ---------- จัดการ POST (เช็คอิน / เช็คเอาท์) ----------
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];

  if ($action === 'checkin') {
    if ($open) {
      $err = 'คุณมีการเข้างานที่ยังไม่ปิดอยู่แล้ว (ห้ามเช็คอินซ้ำ)';
    } else {
      // เข้างาน: บันทึก date_out=time_in ของวันนั้น และ time_out = 00:00:00 เป็น placeholder
      $stmt = $conn->prepare("
        INSERT INTO attendance (user_id, date_in, time_in, date_out, time_out)
        VALUES (?, CURDATE(), CURTIME(), CURDATE(), '00:00:00')
      ");
      $stmt->bind_param('i', $user_id);
      if ($stmt->execute()) $msg = 'เช็คอินเรียบร้อย';
      else $err = 'เช็คอินไม่สำเร็จ';
      $stmt->close();
      // รีโหลดสถานะ
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

// รับข้อความจาก redirect
if (isset($_GET['msg'])) {
  if ($_GET['msg'] === '1') $msg = 'เช็คอินเรียบร้อย';
  if ($_GET['msg'] === '2') $msg = 'เช็คเอาท์เรียบร้อย';
}

// ---------- ดึงประวัติย้อนหลัง (ล่าสุดก่อน) ----------
$logs = [];
$stmt = $conn->prepare("
  SELECT attendance_id, date_in, time_in, date_out, time_out
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
body{
  background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean));
  color:#fff; font-family:"Segoe UI",Tahoma,sans-serif;
}
.wrap{max-width:980px;margin:28px auto;padding:0 14px}
.headbar{
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  border-radius:14px; padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.cardx{
  background:rgba(255,255,255,.09); border:1px solid var(--psu-sritrang);
  border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.22);
}
.stat{
  background:#fff; color:#0D4071; border-radius:14px; padding:16px;
  display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap;
}
.badge-open{background:var(--warn); color:#212529; font-weight:800}
.badge-close{background:var(--ok); color:#fff; font-weight:800}
.btn-ci{background:var(--psu-andaman); border-color:#063d63; font-weight:800}
.btn-ci:hover{background:var(--psu-sky); color:#063d63}
.btn-co{background:#e85f5f; border-color:#a73737; font-weight:800}
.table-logs{background:#fff; color:#0b2746; border-radius:12px; overflow:hidden}
.table-logs thead th{
  background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; font-weight:800;
}
.table-logs td,.table-logs th{ border-color:#e7eefc!important; }
</style>
</head>
<body>
<div class="wrap">
  <div class="headbar d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="h5 m-0 font-weight-bold">ลงเวลาทำงาน • Attendance</div>
      <small class="text-light">ผู้ใช้: <?= h($username ?: $name) ?></small>
    </div>
    <div>
      <a href="../SelectRole/role.php" class="btn btn-sm btn-outline-light">ย้อนกลับ</a>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <?php if($msg): ?>
    <div class="alert" style="background:#2e7d32;color:#fff;border:none;border-radius:10px">
      <?= h($msg) ?>
    </div>
  <?php endif; ?>
  <?php if($err): ?>
    <div class="alert" style="background:#d9534f;color:#fff;border:none;border-radius:10px">
      <?= h($err) ?>
    </div>
  <?php endif; ?>

  <!-- Status card -->
  <div class="cardx p-3 mb-3">
    <div class="stat">
      <div>
        <?php if($open): ?>
          <div class="h5 m-0">สถานะ: <span class="badge badge-open p-2">กำลังเข้างาน</span></div>
          <div class="mt-2">
            เข้างานเมื่อ: <strong><?= h($open['date_in'].' '.$open['time_in']) ?></strong>
          </div>
        <?php else: ?>
          <div class="h5 m-0">สถานะ: <span class="badge badge-close p-2">ว่าง (ไม่ได้เข้างาน)</span></div>
        <?php endif; ?>
      </div>
      <div>
        <form method="post" class="d-inline">
          <input type="hidden" name="action" value="checkin">
          <button type="submit" class="btn btn-ci"
            <?= $open ? 'disabled' : '' ?>>เข้างาน</button>
        </form>
        <form method="post" class="d-inline ml-2">
          <input type="hidden" name="action" value="checkout">
          <button type="submit" class="btn btn-co"
            <?= $open ? '' : 'disabled' ?>>ออกงาน</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Logs -->
  <div class="cardx p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="h5 m-0">บันทึกล่าสุด</div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-logs">
        <thead>
          <tr>
            <th style="width:120px">วันที่เข้างาน</th>
            <th style="width:110px">เวลาเข้า</th>
            <th style="width:120px">วันที่ออกงาน</th>
            <th style="width:110px">เวลาออก</th>
            <th style="width:120px">ชั่วโมงรวม</th>
            <th>สถานะ</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($logs)): ?>
            <tr><td colspan="6" class="text-center text-muted">ยังไม่มีบันทึก</td></tr>
          <?php else: ?>
            <?php foreach($logs as $r):
              $dur = calc_duration($r['date_in'],$r['time_in'],$r['date_out'],$r['time_out']);
              $is_open = (trim($r['time_out'])==='00:00:00');
            ?>
            <tr>
              <td><?= h($r['date_in']) ?></td>
              <td><?= h($r['time_in']) ?></td>
              <td><?= h($r['date_out']) ?></td>
              <td><?= h($r['time_out']) ?></td>
              <td><?= h($dur) ?></td>
              <td>
                <?php if($is_open): ?>
                  <span class="badge badge-warning">กำลังเข้างาน</span>
                <?php else: ?>
                  <span class="badge badge-success">ปิดงานแล้ว</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>

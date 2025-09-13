<?php
// admin/attendance_admin.php — หน้าผู้ดูแลดูเวลาทำงานพนักงาน (ซ่อนบาง role + แสดงชื่อ-รหัสนศ.)
// - ซ่อน role: หน้าร้าน=employee, หลังร้าน=back  (ปรับได้ที่ $HIDE_ROLES)
// - ตาราง/สรุป แสดง "ชื่อ-นามสกุล" + "รหัสนักศึกษา" (แทน username)
// - แสดงคอลัมน์สถานะผู้ใช้ (ชั่วโมงทุน/ชั่วโมงปกติ)

declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
// ถ้าต้องการบังคับเฉพาะ admin ให้เปิดบรรทัดนี้
// if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// ===== Config: ซ่อน role ไหนบ้าง =====
$HIDE_ROLES = ['employee','back']; // 'employee' = หน้าร้าน, 'back' = หลังร้าน

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtHM(int $sec): string { // วินาที -> H:mm
  $h = intdiv($sec, 3600);
  $m = intdiv($sec % 3600, 60);
  return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ชม.';
}
function diff_seconds($date_in, $time_in, $date_out, $time_out): ?int {
  if (trim((string)$time_out) === '00:00:00') return null; // ยังไม่ปิดงาน
  $s = strtotime("$date_in $time_in");
  $e = strtotime("$date_out $time_out");
  if ($s === false || $e === false || $e < $s) return null;
  return $e - $s;
}

// --------- รับตัวกรอง ---------
$today = date('Y-m-d');
$first_of_month = date('Y-m-01');

$start_date = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $first_of_month;
$end_date   = isset($_GET['end'])   && $_GET['end']   !== '' ? $_GET['end']   : $today;
$q = trim((string)($_GET['q'] ?? ''));                // ค้นหา ชื่อ/username
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0; // เลือก user เฉพาะคน

// --------- ดึงรายชื่อพนักงานสำหรับ dropdown (excluding hidden roles) ---------
$users = [];
if (!empty($HIDE_ROLES)) {
  $place = implode(',', array_fill(0, count($HIDE_ROLES), '?'));
  $types = str_repeat('s', count($HIDE_ROLES));
  $stmtU = $conn->prepare("SELECT user_id, username, name, student_ID, role FROM users WHERE role NOT IN ($place) ORDER BY name");
  $stmtU->bind_param($types, ...$HIDE_ROLES);
} else {
  $stmtU = $conn->prepare("SELECT user_id, username, name, student_ID, role FROM users ORDER BY name");
}
$stmtU->execute();
$resU = $stmtU->get_result();
while ($u = $resU->fetch_assoc()) $users[] = $u;
$stmtU->close();

// --------- ดึงบันทึกเวลาตามตัวกรอง (excluding hidden roles) ---------
$sql = "
  SELECT a.attendance_id, a.user_id, a.date_in, a.time_in, a.date_out, a.time_out,
         u.username, u.name, u.student_ID, u.role, u.status
  FROM attendance a
  JOIN users u ON u.user_id = a.user_id
  WHERE a.date_in >= ? AND a.date_in <= ?
";
$params = [$start_date, $end_date];
$types  = 'ss';

if (!empty($HIDE_ROLES)) {
  $sql .= " AND u.role NOT IN (" . implode(',', array_fill(0, count($HIDE_ROLES), '?')) . ")";
  $types .= str_repeat('s', count($HIDE_ROLES));
  $params = array_merge($params, $HIDE_ROLES);
}
if ($user_filter > 0) { $sql .= " AND a.user_id = ?"; $params[] = $user_filter; $types .= 'i'; }
if ($q !== '') {
  $sql .= " AND (u.username LIKE ? OR u.name LIKE ?)";
  $kw = "%$q%"; $params[] = $kw; $params[] = $kw; $types .= 'ss';
}
$sql .= " ORDER BY u.name, a.attendance_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$stmt->close();

// --------- สรุปรวมเวลา/คน และรวมทั้งหมด ---------
$by_user = []; // user_id => ['name','student_ID','status','sec'=>total_seconds]
$total_sec = 0;
foreach ($rows as $r) {
  $sec = diff_seconds($r['date_in'], $r['time_in'], $r['date_out'], $r['time_out']);
  if ($sec !== null) {
    $uid = (int)$r['user_id'];
    if (!isset($by_user[$uid])) {
      $by_user[$uid] = [
        'name'       => $r['name'],
        'student_ID' => $r['student_ID'],
        'status'     => $r['status'] ?? '',
        'sec'        => 0
      ];
    }
    $by_user[$uid]['sec'] += $sec;
    $total_sec += $sec;
  }
}

// จัดเรียงรวมต่อคน ตามชื่อ
uksort($by_user, function($a,$b) use($by_user){
  return strnatcasecmp((string)$by_user[$a]['name'], (string)$by_user[$b]['name']);
});
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Admin • เวลาทำงานพนักงาน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2;  --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ink:#0b2746; --muted:#6b7280;
}
body{background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean));color:#fff;font-family:"Segoe UI",Tahoma,sans-serif;}
.wrap{max-width:1200px;margin:26px auto;padding:0 14px;}
.topbar{
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  border-radius:14px; padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.section{background:rgba(255,255,255,.09); border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.22);}
.badge-chip{background:#fff; color:#0D4071; border-radius:999px; padding:.35rem .6rem; font-weight:800}
.form-control, .custom-select{border:2px solid var(--psu-ocean); background:#fff; color:#111; border-radius:10px;}
.form-control:focus, .custom-select:focus{box-shadow:0 0 0 .2rem rgba(41,171,226,.35)}
.btn-main{background:var(--psu-andaman); border:1px solid #063d63; font-weight:800}
.btn-main:hover{background:var(--psu-sky); color:#063d63}
.table-box{background:#fff; color:var(--ink); border-radius:12px; overflow:hidden}
.table thead th{background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; font-weight:800}
.table td,.table th{border-color:#e7eefc!important}
.subtle{color:#dbeafe}
.summary-card{background:#fff; color:var(--ink); border:1px solid #e7e9f2; border-radius:14px; padding:10px 14px;}
.badge-role{background:#eaf4ff; color:#0D4071; border:1px solid #cfe2ff; border-radius:999px; padding:.15rem .6rem; font-weight:800}
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="h5 m-0 font-weight-bold">Admin • เวลาทำงานพนักงาน</div>
      <small class="subtle">ซ่อนตำแหน่ง: <?= h(implode(', ', $HIDE_ROLES)) ?> • แสดงชื่อ-นามสกุล + รหัสนักศึกษา</small>
    </div>
    <div>
      <a href="../SelectRole/role.php" class="btn btn-sm btn-outline-light mr-2">หน้าหลัก</a>
      <a href="adminmenu.php" class="btn btn-sm btn-outline-light mr-2">เมนูแอดมิน</a>
      <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <!-- Filters -->
  <div class="section p-3 mb-3">
    <form class="row" method="get">
      <div class="col-md-3 mb-2">
        <label class="mb-1">วันที่เริ่ม</label>
        <input type="date" name="start" value="<?= h($start_date) ?>" class="form-control">
      </div>
      <div class="col-md-3 mb-2">
        <label class="mb-1">ถึงวันที่</label>
        <input type="date" name="end" value="<?= h($end_date) ?>" class="form-control">
      </div>
      <div class="col-md-3 mb-2">
        <label class="mb-1">พนักงาน (ถ้าต้องการ)</label>
        <select name="user_id" class="custom-select">
          <option value="0">— ทั้งหมด —</option>
          <?php foreach($users as $u): ?>
            <option value="<?= (int)$u['user_id'] ?>" <?= $user_filter===(int)$u['user_id']?'selected':'' ?>>
              <?= h($u['name'].' • รหัส '.$u['student_ID']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label class="mb-1">ค้นหา (ชื่อ/username)</label>
        <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="เช่น aom, ปริญญ์">
      </div>
      <div class="col-12 d-flex align-items-end">
        <button class="btn btn-main mr-2">กรองข้อมูล</button>
        <a class="btn btn-outline-light mr-2" href="?start=<?= h(date('Y-m-01')) ?>&end=<?= h(date('Y-m-d')) ?>">เดือนนี้</a>
        <a class="btn btn-outline-light mr-2" href="?start=<?= h(date('Y-m-d')) ?>&end=<?= h(date('Y-m-d')) ?>">วันนี้</a>
        <a class="btn btn-outline-light" href="attendance_admin.php">ล้างตัวกรอง</a>
      </div>
    </form>
  </div>

  <!-- Summary -->
  <div class="section p-3 mb-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between">
      <div class="h6 m-0">ช่วงที่เลือก:
        <span class="badge-chip"><?= h($start_date) ?></span>
        <span class="mx-1">→</span>
        <span class="badge-chip"><?= h($end_date) ?></span>
        <?php if($q!==''): ?>
          <span class="badge-chip ml-2">ค้นหา: <?= h($q) ?></span>
        <?php endif; ?>
        <?php if($user_filter>0):
          $uText = '';
          foreach($users as $u){ if((int)$u['user_id']===$user_filter){ $uText = $u['name'].' • รหัส '.$u['student_ID']; break; } }
        ?>
          <span class="badge-chip ml-2">พนักงาน: <?= h($uText) ?></span>
        <?php endif; ?>
      </div>
      <div class="summary-card">
        <strong>รวมเวลาทั้งหมด:</strong>
        <span class="ml-1"><?= fmtHM($total_sec) ?></span>
      </div>
    </div>
  </div>

  <!-- Per-user totals -->
  <div class="section p-3 mb-3">
    <div class="h6 mb-2 font-weight-bold">รวมเวลาต่อพนักงาน</div>
    <div class="table-responsive table-box">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:260px">ชื่อ - นามสกุล</th>
            <th style="width:160px">รหัสนักศึกษา</th>
            <th style="width:160px">สถานะผู้ใช้</th>
            <th style="width:160px" class="text-right">รวมชั่วโมง</th>
          </tr>
        </thead>
        <tbody>
        <?php if(empty($by_user)): ?>
          <tr><td colspan="4" class="text-center text-muted">ไม่พบข้อมูล</td></tr>
        <?php else: ?>
          <?php foreach($by_user as $uid => $u): ?>
            <tr>
              <td><?= h($u['name']) ?></td>
              <td><?= h($u['student_ID']) ?></td>
              <td><span class="badge badge-info p-2"><?= h($u['status'] ?: '-') ?></span></td>
              <td class="text-right"><strong><?= fmtHM($u['sec']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Raw logs -->
  <div class="section p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="h6 m-0 font-weight-bold">บันทึกเวลา (รายการ)</div>
    </div>
    <div class="table-responsive table-box">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:260px">ชื่อ - นามสกุล</th>
            <th style="width:160px">รหัสนักศึกษา</th>
            <th style="width:140px">วันที่เข้า</th>
            <th style="width:110px">เวลาเข้า</th>
            <th style="width:140px">วันที่ออก</th>
            <th style="width:110px">เวลาออก</th>
            <th style="width:130px" class="text-right">ชั่วโมงรวม</th>
            <th style="width:140px">สถานะผู้ใช้</th>
            <th style="width:120px">สถานะงาน</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted">ไม่พบข้อมูล</td></tr>
          <?php else: ?>
            <?php foreach($rows as $r):
              $sec = diff_seconds($r['date_in'],$r['time_in'],$r['date_out'],$r['time_out']);
              $dur = $sec===null ? '-' : fmtHM($sec);
              $open = (trim($r['time_out'])==='00:00:00');
            ?>
              <tr>
                <td><?= h($r['name']) ?></td>
                <td><?= h($r['student_ID']) ?></td>
                <td><?= h($r['date_in']) ?></td>
                <td><?= h($r['time_in']) ?></td>
                <td><?= h($r['date_out']) ?></td>
                <td><?= h($r['time_out']) ?></td>
                <td class="text-right"><?= h($dur) ?></td>
                <td><span class="badge badge-info p-2"><?= h($r['status'] ?: '-') ?></span></td>
                <td>
                  <?php if($open): ?>
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

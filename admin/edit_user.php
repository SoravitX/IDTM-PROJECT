<?php
// admin/edit_user.php — แก้ไขผู้ใช้ (PSU tone)
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* (แนะนำ) จำกัดสิทธิ์เฉพาะ admin */
$allow_roles = ['admin'];
if (!empty($_SESSION['role']) && !in_array($_SESSION['role'], $allow_roles, true)) {
  header('Location: ../index.php'); exit;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$err = '';
$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { header('Location: users_list.php'); exit; }

// ----- โหลดข้อมูลเดิม -----
// ✅ ดึง status มาด้วย
$stmt = $conn->prepare("SELECT user_id, username, student_ID, name, role, status FROM users WHERE user_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { header('Location: users_list.php'); exit; }

// ตัวเลือกบทบาท
$roles = [
  'admin'   => 'ผู้ดูแล',
  'employee'=> 'พนักงาน',
  'kitchen' => 'ครัว',
  'back'    => 'หลังร้าน',
  'barista' => 'บาริสต้า'
];

// ตัวเลือกสถานะชั่วโมง
$status_options = ['ชั่วโมงทุน','ชั่วโมงปกติ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $student_ID = trim((string)($_POST['student_id'] ?? ''));
  $username   = trim((string)($_POST['username'] ?? ''));
  $name       = trim((string)($_POST['name'] ?? ''));
  $role       = (string)($_POST['role'] ?? 'employee');
  $status     = (string)($_POST['status'] ?? 'ชั่วโมงปกติ'); // ✅ รับสถานะจากฟอร์ม
  $pass       = (string)($_POST['password'] ?? '');
  $pass2      = (string)($_POST['password2'] ?? '');

  // validate
  if ($student_ID === '' || $username === '' || $name === '') {
    $err = 'กรอกข้อมูลให้ครบ';
  } elseif (!isset($roles[$role])) {
    $err = 'บทบาทไม่ถูกต้อง';
  } elseif (!in_array($status, $status_options, true)) {
    $err = 'สถานะไม่ถูกต้อง';
  } elseif ($pass !== '' && $pass !== $pass2) {
    $err = 'รหัสผ่านใหม่ไม่ตรงกัน';
  } else {
    // ตรวจซ้ำ username (ยกเว้นตัวเอง)
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE username=? AND user_id<>? LIMIT 1");
    $stmt->bind_param("si", $username, $id);
    $stmt->execute();
    $dupUser = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    // ตรวจซ้ำ student_ID (ยกเว้นตัวเอง)
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE student_ID=? AND user_id<>? LIMIT 1");
    $stmt->bind_param("si", $student_ID, $id);
    $stmt->execute();
    $dupSID = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if ($dupUser) {
      $err = 'มีชื่อผู้ใช้นี้อยู่แล้ว';
    } elseif ($dupSID) {
      $err = 'มีรหัสนักศึกษานี้อยู่แล้ว';
    } else {
      // อัปเดต
      if ($pass !== '') {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        // ✅ อัปเดต status ด้วย
        $stmt = $conn->prepare("
          UPDATE users
          SET username=?, student_ID=?, name=?, role=?, status=?, password=?
          WHERE user_id=?
        ");
        $stmt->bind_param("ssssssi", $username, $student_ID, $name, $role, $status, $hash, $id);
      } else {
        $stmt = $conn->prepare("
          UPDATE users
          SET username=?, student_ID=?, name=?, role=?, status=?
          WHERE user_id=?
        ");
        $stmt->bind_param("sssssi", $username, $student_ID, $name, $role, $status, $id);
      }
      $stmt->execute();
      $stmt->close();

      header("Location: users_list.php?msg=updated");
      exit;
    }
  }

  // ถ้า validate ไม่ผ่าน — คงค่าที่ผู้ใช้กรอกไว้
  $user['username']   = $username;
  $user['student_ID'] = $student_ID;
  $user['name']       = $name;
  $user['role']       = $role;
  $user['status']     = $status;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แก้ไขผู้ใช้ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
:root{ --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-sky:#29ABE2; --psu-sritrang:#BBB4D8; }
body{ background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean)); color:#fff; font-family:"Segoe UI",Tahoma,Arial }
.pos-shell{ max-width:900px; margin:18px auto; padding:0 12px }
.topbar{ position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18) }
.brand{ font-weight:900 }
.cardx{ background:#fff; color:#0b2746; border:1px solid #e3ecff; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.22) }
.badge-user{ background:#4173BD; color:#fff; font-weight:800; border-radius:999px }
.btn-ghost{ background:#0094B3; border:1px solid #063d63; color:#fff; font-weight:700 }
.form-control, .custom-select{ border-radius:10px; }
</style>
</head>
<body>
<div class="pos-shell">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • Admin</h4>
      <span class="badge badge-user px-3 py-2">แก้ไขผู้ใช้ #<?= (int)$user['user_id'] ?></span>
    </div>
    <div class="d-flex align-items-center">
      <a href="users_list.php" class="btn btn-light btn-sm mr-2">← รายชื่อผู้ใช้</a>
      <a href="adminmenu.php" class="btn btn-light btn-sm mr-2">หน้า Admin</a>
      <a href="../logout.php" class="btn btn-outline-light btn-sm">ออกจากระบบ</a>
    </div>
  </div>

  <div class="cardx p-3">
    <?php if ($err): ?>
      <div class="alert alert-danger mb-3"><?= h($err) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="id" value="<?= (int)$user['user_id'] ?>">

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>รหัสนักศึกษา (Student ID) <span class="text-danger">*</span></label>
          <input type="text" name="student_id" class="form-control" required
                 value="<?= h($user['student_ID']) ?>">
        </div>
        <div class="form-group col-md-6">
          <label>ชื่อผู้ใช้ (username) <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" required
                 value="<?= h($user['username']) ?>">
        </div>

        <div class="form-group col-md-6">
          <label>ชื่อ - สกุล ที่แสดง <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required
                 value="<?= h($user['name']) ?>">
        </div>

        <div class="form-group col-md-6">
          <label>บทบาท (role)</label>
          <select name="role" class="custom-select">
            <?php foreach($roles as $k=>$v): ?>
              <option value="<?= h($k) ?>" <?= ($user['role']===$k)?'selected':'' ?>>
                <?= h($v) ?> (<?= h($k) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- ✅ สถานะชั่วโมง -->
        <div class="form-group col-md-6">
          <label>สถานะชั่วโมง</label>
          <select name="status" class="custom-select">
            <?php foreach ($status_options as $opt): ?>
              <option value="<?= h($opt) ?>" <?= ($user['status'] === $opt ? 'selected' : '') ?>>
                <?= h($opt) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>รหัสผ่านใหม่ (เว้นว่างถ้าไม่เปลี่ยน)</label>
          <input type="password" name="password" class="form-control" minlength="4">
        </div>
        <div class="form-group col-md-6">
          <label>ยืนยันรหัสผ่านใหม่</label>
          <input type="password" name="password2" class="form-control" minlength="4">
        </div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="users_list.php" class="btn btn-light">ยกเลิก</a>
        <button class="btn btn-ghost">บันทึกการแก้ไข</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', function(e){
  const pw  = this.querySelector('input[name="password"]').value;
  const pw2 = this.querySelector('input[name="password2"]').value;
  if (pw !== '' && pw !== pw2) { e.preventDefault(); alert('รหัสผ่านใหม่ไม่ตรงกัน'); }
});
</script>
</body>
</html>

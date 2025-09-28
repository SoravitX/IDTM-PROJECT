<?php declare(strict_types=1);
// admin/add_user.php — สร้างผู้ใช้ใหม่ (Dark Neon PSU-ish tone)
session_start();
require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* (แนะนำ) จำกัดสิทธิ์เฉพาะ admin */
$allow_roles = ['admin'];
if (!empty($_SESSION['role']) && !in_array($_SESSION['role'], $allow_roles, true)) {
  header('Location: ../index.php'); exit;
}

$err = '';

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
  $status     = (string)($_POST['status'] ?? 'ชั่วโมงปกติ');
  $pass       = (string)($_POST['password'] ?? '');
  $pass2      = (string)($_POST['password2'] ?? '');

  // validate
  if ($student_ID === '' || $username === '' || $name === '' || $pass === '' || $pass2 === '') {
    $err = 'กรอกข้อมูลให้ครบ';
  } elseif ($pass !== $pass2) {
    $err = 'รหัสผ่านไม่ตรงกัน';
  } elseif (!isset($roles[$role])) {
    $err = 'บทบาทไม่ถูกต้อง';
  } elseif (!in_array($status, $status_options, true)) {
    $err = 'สถานะไม่ถูกต้อง';
  } else {
    // ตรวจซ้ำ username
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $dupUser = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    // ตรวจซ้ำ student_ID
    $stmt = $conn->prepare("SELECT 1 FROM users WHERE student_ID=? LIMIT 1");
    $stmt->bind_param("s", $student_ID);
    $stmt->execute();
    $dupSID = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();

    if ($dupUser) {
      $err = 'มีชื่อผู้ใช้นี้อยู่แล้ว';
    } elseif ($dupSID) {
      $err = 'มีรหัสนักศึกษานี้อยู่แล้ว';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("
        INSERT INTO users (username, password, student_ID, name, role, status)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $stmt->bind_param("ssssss", $username, $hash, $student_ID, $name, $role, $status);
      $stmt->execute();
      $stmt->close();
      header("Location: users_list.php?msg=added");
      exit;
    }
  }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>สร้างผู้ใช้ใหม่ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
.pos-shell{ max-width:960px; margin:20px auto; padding:0 14px }

/* ===== Topbar ===== */
.topbar{
  position:sticky; top:0; z-index:50;
  padding:12px 16px; border-radius:14px;
  background:rgba(35,42,49,.72); backdrop-filter: blur(6px);
  border:1px solid rgba(255,255,255,.06);
  box-shadow:0 10px 26px rgba(0,0,0,.35);
}
.brand{ font-weight:900; color:var(--brand-900); letter-spacing:.3px }
.badge-user{
  background: linear-gradient(180deg, rgba(0,173,181,.25), rgba(0,173,181,.1));
  color:var(--brand-300); font-weight:800; border-radius:999px; border:1px solid rgba(0,173,181,.35)
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

/* ===== Form ===== */
label{ font-weight:800; color:var(--brand-900) }
.form-control, .custom-select{
  color:var(--text-strong);
  background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.10);
  border-radius:12px;
}
.form-control::placeholder{ color:var(--text-muted) }
.form-control:focus, .custom-select:focus{
  border-color: var(--brand-500);
  box-shadow:0 0 0 .2rem rgba(0,173,181,.25);
  background: #2F373F;
}

/* Buttons */
.btn-ghost{
  background:linear-gradient(180deg, var(--brand-500), #07949B);
  color:#061217; font-weight:900; border:0; border-radius:12px;
  box-shadow:0 8px 22px rgba(0,173,181,.25);
}
.btn-ghost:hover{ filter:brightness(1.03) }
.btn-light{
  background:transparent; color:var(--brand-700);
  border:1px solid rgba(255,255,255,.18); border-radius:12px; font-weight:800
}
.btn-outline-light{ border-radius:12px }

/* Alert */
.alert{
  border-radius:12px; font-weight:700;
  background:rgba(229,57,53,.12); color:#ffc9c9; border-color:rgba(229,57,53,.35)
}

/* Small helpers */
.help{ color:var(--text-muted); font-size:.9rem }
.hr-soft{ border-top:1px solid rgba(255,255,255,.08) }

/* Accessibility */
:focus-visible{ outline:3px solid rgba(0,173,181,.45); outline-offset:3px; border-radius:10px }

/* Scrollbar */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e353c;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#394148}
*::-webkit-scrollbar-track{background:#1c2127}
</style>
</head>
<body>
<div class="pos-shell">

  <!-- Topbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3"><i class="bi bi-shield-lock"></i> PSU Blue Cafe • Admin</h4>
      <span class="badge badge-user px-3 py-2"><i class="bi bi-person-plus"></i> สร้างผู้ใช้ใหม่</span>
    </div>
    <div class="d-flex align-items-center" style="gap:8px">
      <a href="users_list.php" class="btn btn-sm">ดูผู้ใช้ทั้งหมด</a>
      <a href="adminmenu.php" class="btn btn-sm">← กลับหน้า Admin</a>
      <a href="../logout.php" class="btn btn-outline-light btn-sm">ออกจากระบบ</a>
    </div>
  </div>

  <!-- Card -->
  <div class="cardx p-3 p-md-4">
    <?php if ($err): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($err,ENT_QUOTES,'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-row">
        <div class="form-group col-md-6">
          <label>รหัสนักศึกษา (Student ID) <span class="text-danger">*</span></label>
          <input type="text" name="student_id" class="form-control" required
                 placeholder="เช่น 66xxxxxxx"
                 value="<?= htmlspecialchars($_POST['student_id'] ?? '', ENT_QUOTES,'UTF-8') ?>">
          <small class="help">ใช้สำหรับระบุผู้ใช้ภายในระบบ</small>
        </div>

        <div class="form-group col-md-6">
          <label>ชื่อผู้ใช้ (username) <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" required
                 placeholder="ตัวอย่าง: panit123"
                 value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES,'UTF-8') ?>">
        </div>

        <div class="form-group col-md-6">
          <label>ชื่อ - สกุล ที่แสดง <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required
                 placeholder="ตัวอย่าง: สมหญิง ใจดี"
                 value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES,'UTF-8') ?>">
        </div>

        <div class="form-group col-md-6">
          <label>บทบาท (role)</label>
          <select name="role" class="custom-select">
            <?php foreach($roles as $k=>$v): ?>
              <option value="<?= htmlspecialchars($k,ENT_QUOTES) ?>"
                <?= (($_POST['role'] ?? 'employee')===$k)?'selected':'' ?>>
                <?= htmlspecialchars($v,ENT_QUOTES) ?> (<?= htmlspecialchars($k,ENT_QUOTES) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group col-md-6">
          <label>สถานะชั่วโมง</label>
          <select name="status" class="custom-select">
            <?php foreach ($status_options as $opt): ?>
              <option value="<?= htmlspecialchars($opt,ENT_QUOTES,'UTF-8') ?>"
                <?= (($_POST['status'] ?? 'ชั่วโมงปกติ')===$opt)?'selected':'' ?>>
                <?= htmlspecialchars($opt,ENT_QUOTES,'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="hr-soft">

      <div class="form-row">
        <div class="form-group col-md-6">
          <label>รหัสผ่าน <span class="text-danger">*</span></label>
          <input type="password" name="password" class="form-control" required minlength="4" placeholder="อย่างน้อย 4 ตัวอักษร">
        </div>
        <div class="form-group col-md-6">
          <label>ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
          <input type="password" name="password2" class="form-control" required minlength="4" placeholder="พิมพ์รหัสผ่านอีกครั้ง">
        </div>
      </div>

      <div class="d-flex justify-content-between mt-2">
        <a href="adminmenu.php" class="btn btn-light"><i class="bi bi-x-circle"></i> ยกเลิก</a>
        <button class="btn btn-ghost"><i class="bi bi-save"></i> บันทึกผู้ใช้</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', function(e){
  const pw  = this.querySelector('input[name="password"]').value;
  const pw2 = this.querySelector('input[name="password2"]').value;
  if (pw !== pw2) { e.preventDefault(); alert('รหัสผ่านไม่ตรงกัน'); }
});
</script>
</body>
</html>

<?php declare(strict_types=1); 
// admin/edit_user.php — แก้ไขผู้ใช้ (Navy × Violet Neon tone • Fix: ตรวจซ้ำเฉพาะเมื่อแก้ student_ID/username)
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

/* ----- โหลดข้อมูลเดิม (ดึง status มาด้วย) ----- */
$stmt = $conn->prepare("SELECT user_id, username, student_ID, name, role, status FROM users WHERE user_id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { header('Location: users_list.php'); exit; }

/* เก็บค่าเดิมไว้เพื่อใช้เทียบว่ามีการแก้ไขจริงหรือไม่ */
$orig_username   = (string)$user['username'];
$orig_student_ID = (string)$user['student_ID'];

/* ตัวเลือกบทบาท */
$roles = [
  'admin'   => 'ผู้ดูแล',
  'employee'=> 'พนักงาน',
  'kitchen' => 'ครัว',
  'back'    => 'หลังร้าน',
  'barista' => 'บาริสต้า'
];

/* ตัวเลือกสถานะชั่วโมง */
$status_options = ['ชั่วโมงทุน','ชั่วโมงปกติ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $student_ID = trim((string)($_POST['student_id'] ?? ''));
  $username   = trim((string)($_POST['username'] ?? ''));
  $name       = trim((string)($_POST['name'] ?? ''));
  $role       = (string)($_POST['role'] ?? 'employee');
  $status     = (string)($_POST['status'] ?? 'ชั่วโมงปกติ');
  $pass       = (string)($_POST['password'] ?? '');
  $pass2      = (string)($_POST['password2'] ?? '');

  /* ตรวจว่าเปลี่ยนค่าจริงไหม */
  $username_changed   = ($username !== $orig_username);
  $student_ID_changed = ($student_ID !== $orig_student_ID);

  /* validate เบื้องต้น */
  if ($student_ID === '' || $username === '' || $name === '') {
    $err = 'กรอกข้อมูลให้ครบ';
  } elseif (!isset($roles[$role])) {
    $err = 'บทบาทไม่ถูกต้อง';
  } elseif (!in_array($status, $status_options, true)) {
    $err = 'สถานะไม่ถูกต้อง';
  } elseif ($pass !== '' && $pass !== $pass2) {
    $err = 'รหัสผ่านใหม่ไม่ตรงกัน';
  } else {
    /* ตรวจซ้ำเฉพาะฟิลด์ที่มีการเปลี่ยนแปลงจริง */
    $dupUser = false;
    $dupSID  = false;

    if ($username_changed) {
      $stmt = $conn->prepare("SELECT 1 FROM users WHERE username=? AND user_id<>? LIMIT 1");
      $stmt->bind_param("si", $username, $id);
      $stmt->execute();
      $dupUser = (bool)$stmt->get_result()->fetch_row();
      $stmt->close();
    }

    if ($student_ID_changed) {
      $stmt = $conn->prepare("SELECT 1 FROM users WHERE student_ID=? AND user_id<>? LIMIT 1");
      $stmt->bind_param("si", $student_ID, $id);
      $stmt->execute();
      $dupSID = (bool)$stmt->get_result()->fetch_row();
      $stmt->close();
    }

    if ($dupUser) {
      $err = 'มีชื่อผู้ใช้นี้อยู่แล้ว';
    } elseif ($dupSID) {
      $err = 'มีรหัสนักศึกษานี้อยู่แล้ว';
    } else {
      /* อัปเดต */
      if ($pass !== '') {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
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

  /* ถ้า validate ไม่ผ่าน — คงค่าที่ผู้ใช้กรอกไว้ */
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
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* ===== Navy × Violet Neon Theme ===== */
:root{
  --text-strong:#EEF2FF;   /* ข้อความหลัก โทนอ่อนอ่านง่ายบนพื้นเข้ม */
  --text-normal:#E5E7EB;
  --text-muted:#A3A8B8;

  --bg-grad1:#0B1220;      /* background ไล่เฉดน้ำเงินเข้ม */
  --bg-grad2:#111827;

  --surface:#111423;       /* cards */
  --surface-2:#0f1422;
  --surface-3:#151a2b;

  --ink:#F8FAFC;
  --ink-muted:#CBD5E1;

  /* Accent: Violet */
  --brand-900:#EDE9FE;
  --brand-700:#C4B5FD;
  --brand-500:#7C3AED;   /* main accent */
  --brand-400:#A78BFA;
  --brand-300:#C7B9FF;

  --ok:#22c55e;
  --danger:#ef4444;

  --ring:rgba(124,58,237,.45);  /* violet glow */
  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

/* ===== Page scaffold ===== */
html,body{height:100%}
body{
  background:
    radial-gradient(900px 360px at 110% -10%, rgba(124,58,237,.20), transparent 65%),
    radial-gradient(800px 420px at 10% 20%, rgba(39,75,245,.12), transparent 60%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.pos-shell{ max-width:980px; margin:20px auto; padding:0 14px }

/* ===== Topbar ===== */
.topbar{
  position:sticky; top:0; z-index:50;
  padding:12px 16px; border-radius:14px;
  background:color-mix(in oklab, var(--surface), black 5%); 
  backdrop-filter: blur(6px);
  border:1px solid rgba(167,139,250,.25);
  box-shadow:var(--shadow-lg);
}
.brand{ font-weight:900; color:var(--brand-300); letter-spacing:.35px }
.badge-user{
  background:linear-gradient(180deg, rgba(124,58,237,.22), rgba(124,58,237,.10));
  color:var(--brand-700); font-weight:800; border-radius:999px; 
  border:1px solid rgba(167,139,250,.45)
}
.topbar .btn{
  border-radius:12px; font-weight:800;
  border:1px solid rgba(226,232,240,.12); color:var(--text-normal);
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
}
.topbar .btn:hover{ filter:brightness(1.08) }

/* ===== Card ===== */
.cardx{
  background: linear-gradient(180deg,var(--surface),var(--surface-2));
  color:var(--ink);
  border:1px solid rgba(167,139,250,.18);
  border-radius:16px; box-shadow:var(--shadow);
}

/* ===== Forms ===== */
label{ font-weight:800; color:var(--brand-900) }
.help{ color:var(--text-muted); font-size:.9rem }
.hr-soft{ border-top:1px dashed rgba(167,139,250,.22) }

.form-control, .custom-select{
  color:var(--text-strong);
  background:var(--surface-3);
  border:1.5px solid rgba(167,139,250,.20);
  border-radius:12px;
}
.form-control::placeholder{ color:var(--text-muted) }
.form-control:focus, .custom-select:focus{
  border-color: var(--brand-400);
  box-shadow:0 0 0 .22rem var(--ring);
  background:#161c2f;
}

/* Input with leading icon */
.input-icon{ position:relative; }
.input-icon > .bi{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  color:var(--brand-400); opacity:.95; pointer-events:none;
}
.input-icon > .form-control{ padding-left:38px; }

/* Buttons */
.btn-ghost{
  background:linear-gradient(180deg, var(--brand-500), #5B21B6);
  color:#F8FAFC; font-weight:900; border:0; border-radius:12px;
  box-shadow:0 10px 26px rgba(124,58,237,.28);
}
.btn-ghost:hover{ filter:brightness(1.05) }
.btn-light{
  background:transparent; color:#d6d6ff;
  border:1px solid rgba(167,139,250,.25); border-radius:12px; font-weight:800
}
.btn-outline-secondary{
  background:transparent; color:var(--ink);
  border:1px solid rgba(226,232,240,.20); border-radius:10px; font-weight:800
}

/* Alert */
.alert{
  border-radius:12px; font-weight:700;
  background:rgba(239,68,68,.12); color:#fecaca; border-color:rgba(239,68,68,.35)
}

/* Avatar */
.avatar{
  width:42px;height:42px;border-radius:50%;
  background:linear-gradient(180deg,#1b2034,#12182b);
  color:var(--brand-400); display:flex; align-items:center; justify-content:center;
  font-weight:900; box-shadow: inset 0 1px 0 rgba(255,255,255,.06), 0 8px 18px rgba(0,0,0,.35);
}

/* Accessibility & Scrollbar */
:focus-visible{ outline:3px solid var(--ring); outline-offset:3px; border-radius:10px }
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#272b3f;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#303555}
*::-webkit-scrollbar-track{background:#14182a}
:root{
  --text-strong:#ffffff;
  --text-normal:#e9eef6;
  --text-muted:#b9c6d6;

  --bg-grad1:#11161b;     /* background */
  --bg-grad2:#141b22;

  --surface:#1a2230;      /* cards */
  --surface-2:#192231;
  --surface-3:#202a3a;

  --ink:#e9eef6;
  --ink-muted:#b9c6d6;

  --brand-900:#f1f6ff;
  --brand-700:#d0d9e6;
  --brand-500:#3aa3ff;    /* accent */
  --brand-400:#7cbcfd;
  --brand-300:#a9cffd;

  --ok:#22c55e; 
  --danger:#e53935;

  --shadow-lg:none;
  --shadow:none;
}

/* ===== Page scaffold ===== */
html,body{height:100%}
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.pos-shell{ max-width:980px; margin:20px auto; padding:0 14px }

/* ===== Topbar ===== */
.topbar{
  position:sticky; top:0; z-index:50;
  padding:12px 16px; border-radius:14px;
  background:var(--surface-2); backdrop-filter: blur(6px);
  border:1px solid rgba(255,255,255,.08);
  box-shadow:none;
}
.brand{ font-weight:900; color:var(--brand-300); letter-spacing:.3px }
.badge-user{
  background: var(--brand-500);
  color:#fff; font-weight:800; border-radius:999px; 
  border:1px solid #1e6acc;
}
.topbar .btn{
  border-radius:12px; font-weight:800;
  border:1px solid rgba(255,255,255,.18); color:#fff;
  background: var(--surface-3);
}
.topbar .btn:hover{ filter:brightness(1.08) }

/* ===== Card ===== */
.cardx{
  background: var(--surface);
  color:var(--ink);
  border:1px solid rgba(255,255,255,.08);
  border-radius:16px; box-shadow:none;
}

/* ===== Forms ===== */
label{ font-weight:800; color:var(--brand-300) }
.help{ color:var(--text-muted); font-size:.9rem }
.hr-soft{ border-top:1px dashed rgba(255,255,255,.12) }

.form-control, .custom-select{
  color:var(--text-strong);
  background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.12);
  border-radius:12px;
}
.form-control::placeholder{ color:var(--text-muted) }
.form-control:focus, .custom-select:focus{
  border-color: var(--brand-500);
  box-shadow:0 0 0 .2rem rgba(58,163,255,.25);
  background:#202a3a;
}

/* Input with leading icon */
.input-icon{ position:relative; }
.input-icon > .bi{
  position:absolute; left:12px; top:50%; transform:translateY(-50%);
  color:var(--brand-400); opacity:.9; pointer-events:none;
}
.input-icon > .form-control{ padding-left:38px; }

/* Buttons */
.btn-ghost{
  background: var(--brand-500);
  color:#fff; font-weight:900; border:1px solid #1e6acc; border-radius:12px;
}
.btn-ghost:hover{ filter:brightness(1.05) }
.btn-light{
  background:transparent; color:var(--brand-300);
  border:1px solid rgba(255,255,255,.18); border-radius:12px; font-weight:800
}
.btn-outline-secondary{ border-radius:12px; color:var(--text-normal); border-color:rgba(255,255,255,.18) }

/* Alert */
.alert{
  border-radius:12px; font-weight:700;
  background:rgba(229,57,53,.12); color:#ffc9c9; border-color:rgba(229,57,53,.35)
}

/* Avatar */
.avatar{
  width:42px;height:42px;border-radius:50%;
  background:var(--surface-3);
  color:var(--brand-300); display:flex; align-items:center; justify-content:center;
  font-weight:900; box-shadow:none;
}

/* Accessibility & Scrollbar */
:focus-visible{ outline:3px solid rgba(58,163,255,.45); outline-offset:3px; border-radius:10px }
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#3a4752}
*::-webkit-scrollbar-track{background:#151a20}

</style>
</head>
<body>
<div class="pos-shell">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3"><i class="bi bi-shield-lock"></i> PSU Blue Cafe • Admin</h4>
      <span class="badge badge-user px-3 py-2">แก้ไขผู้ใช้ #<?= (int)$user['user_id'] ?></span>
    </div>
    <div class="d-flex align-items-center" style="gap:8px">
      <a href="users_list.php" class="btn btn-sm"><i class="bi bi-people"></i> รายชื่อผู้ใช้</a>
      <a href="adminmenu.php" class="btn btn-sm"><i class="bi bi-grid"></i> หน้า Admin</a>
      <a href="../logout.php" class="btn btn-sm"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <div class="cardx p-3 p-md-4">
    <?php if ($err): ?>
      <div class="alert alert-danger mb-3"><?= h($err) ?></div>
    <?php endif; ?>

    <div class="d-flex align-items-center mb-3">
      <div class="avatar mr-2">
        <?= strtoupper(mb_substr(trim($user['name']!==''?$user['name']:$user['username']),0,1,'UTF-8')) ?>
      </div>
      <div>
        <div class="font-weight-bold" style="color:var(--brand-900)"><?= h($user['name'] ?: $user['username']) ?></div>
        <div class="text-muted small">User ID: <?= (int)$user['user_id'] ?></div>
      </div>
    </div>

    <form method="post" novalidate id="editForm">
      <input type="hidden" name="id" value="<?= (int)$user['user_id'] ?>">

      <div class="form-row">
        <div class="form-group col-md-6 input-icon">
          <i class="bi bi-hash"></i>
          <label>รหัสนักศึกษา (Student ID) <span class="text-danger">*</span></label>
          <input type="text" name="student_id" class="form-control" required
                 value="<?= h($user['student_ID']) ?>" autocomplete="off" placeholder="66xxxxxxx">
          <small class="help">ต้องไม่ซ้ำกับผู้ใช้อื่น</small>
        </div>
        <div class="form-group col-md-6 input-icon">
          <i class="bi bi-person-badge"></i>
          <label>ชื่อผู้ใช้ (username) <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" required
                 value="<?= h($user['username']) ?>" autocomplete="off" placeholder="เช่น panit123">
          <small class="help">ใช้สำหรับเข้าสู่ระบบ</small>
        </div>

        <div class="form-group col-md-6 input-icon">
          <i class="bi bi-card-text"></i>
          <label>ชื่อ - สกุล ที่แสดง <span class="text-danger">*</span></label>
          <input type="text" name="name" class="form-control" required
                 value="<?= h($user['name']) ?>" placeholder="สมหญิง ใจดี">
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

      <hr class="hr-soft">

      <div class="form-row">
        <div class="form-group col-md-6 input-icon">
          <i class="bi bi-lock"></i>
          <label>รหัสผ่านใหม่ <small class="text-muted">(เว้นว่างถ้าไม่เปลี่ยน)</small></label>
          <div class="input-group">
            <input type="password" name="password" id="pw" class="form-control" minlength="4" autocomplete="new-password" placeholder="อย่างน้อย 4 ตัวอักษร">
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="togglePw"><i class="bi bi-eye"></i></button>
            </div>
          </div>
          <small class="help">ปลอดภัยขึ้นด้วยรหัสผ่านที่เดายาก</small>
        </div>
        <div class="form-group col-md-6 input-icon">
          <i class="bi bi-lock-fill"></i>
          <label>ยืนยันรหัสผ่านใหม่</label>
          <div class="input-group">
            <input type="password" name="password2" id="pw2" class="form-control" minlength="4" autocomplete="new-password" placeholder="พิมพ์รหัสผ่านอีกครั้ง">
            <div class="input-group-append">
              <button class="btn btn-outline-secondary" type="button" id="togglePw2"><i class="bi bi-eye"></i></button>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center">
        <a href="users_list.php" class="btn btn-light"><i class="bi bi-arrow-left"></i> ยกเลิก</a>
        <button class="btn btn-ghost"><i class="bi bi-check2-circle"></i> บันทึกการแก้ไข</button>
      </div>
    </form>
  </div>
</div>

<script>
// ตรวจรหัสผ่านตรงกันก่อนส่ง
document.getElementById('editForm')?.addEventListener('submit', function(e){
  const pw  = this.querySelector('input[name="password"]').value;
  const pw2 = this.querySelector('input[name="password2"]').value;
  if (pw !== '' && pw !== pw2) { e.preventDefault(); alert('รหัสผ่านใหม่ไม่ตรงกัน'); }
});

// แสดง/ซ่อนรหัสผ่าน
function togglePassword(id, btnId){
  const inp = document.getElementById(id);
  const btn = document.getElementById(btnId);
  if(!inp || !btn) return;
  btn.addEventListener('click', ()=>{
    const showing = inp.type === 'text';
    inp.type = showing ? 'password' : 'text';
    const i = btn.querySelector('i');
    if(i) i.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
}
togglePassword('pw','togglePw');
togglePassword('pw2','togglePw2');

// เตือนออกหน้าถ้าแก้ไขแล้วไม่บันทึก
let dirty = false;
Array.from(document.querySelectorAll('#editForm input, #editForm select')).forEach(el=>{
  el.addEventListener('change', ()=> dirty = true);
  el.addEventListener('input',  ()=> dirty = true);
});
window.addEventListener('beforeunload', function (e) {
  if (!dirty) return;
  e.preventDefault(); e.returnValue = '';
});
// ถ้ากด submit ถือว่าบันทึกแล้ว
document.getElementById('editForm')?.addEventListener('submit', ()=>{ dirty=false; });

// โฟกัสช่องแรกแบบไว
document.querySelector('input[name="student_id"]')?.focus();
</script>
</body>
</html>

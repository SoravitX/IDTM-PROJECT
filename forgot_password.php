<?php
// forgot_password.php — Reset ด้วย username + ตั้งรหัสใหม่ (bcrypt)
session_start();
require __DIR__ . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pw1      = (string)($_POST['password'] ?? '');
    $pw2      = (string)($_POST['password2'] ?? '');

    if ($username === '' || $pw1 === '' || $pw2 === '') {
        $error = 'กรอกข้อมูลให้ครบถ้วน';
    } elseif ($pw1 !== $pw2) {
        $error = 'รหัสผ่านใหม่ไม่ตรงกัน';
    } elseif (mb_strlen($pw1) < 4) {
        $error = 'รหัสผ่านใหม่ควรมีอย่างน้อย 4 ตัวอักษร';
    } else {
        // หา user ตาม username (ห้ามกรองด้วย password)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $error = 'ไม่พบบัญชีผู้ใช้ชื่อนี้';
        } else {
            // อัปเดตรหัสเป็น bcrypt
            $hash = password_hash($pw1, PASSWORD_BCRYPT, ['cost'=>11]);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param("si", $hash, $user['user_id']);
            $stmt->execute();
            $stmt->close();

            // เสร็จแล้วส่งกลับหน้า login พร้อมข้อความ
            header("Location: index.php?msg=reset_ok");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>ตั้งรหัสผ่านใหม่ • PSU Blue Cafe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap CSS -->
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
  <!-- Bootstrap Icons -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <!-- Kanit font -->
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
  :root{
    /* ===== โทนมินิมอลเข้มน้ำเงิน (match หน้า login) ===== */
    --bg-grad1:#11161b; --bg-grad2:#141b22;
    --surface:#1a2230;  --surface-2:#192231; --surface-3:#202a3a;
    --ink:#e9eef6; --ink-muted:#b9c6d6; --text-strong:#ffffff;

    --brand-500:#3aa3ff; --brand-400:#7cbcfd; --brand-300:#a9cffd;

    --danger:#e53935;
    --radius:16px;
  }

  html, body { height:100% }
  body{
    margin:0; font-family: 'Kanit', system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; color:var(--ink);
    background:
      radial-gradient(900px 420px at 85% 30%, rgba(58,163,255,.08), transparent 60%),
      radial-gradient(1000px 520px at 10% 10%, rgba(121,188,253,.10), transparent 60%),
      linear-gradient(135deg, var(--bg-grad1), var(--bg-grad2));
    display:flex; align-items:center; justify-content:center; padding:18px;
  }

  .wrap{ width:100%; max-width:500px; }
  .cardx{
    background: var(--surface);
    border:1px solid rgba(255,255,255,.08);
    color:var(--ink);
    border-radius:var(--radius);
    box-shadow:none;
    overflow:hidden;
  }
  .cardx-header{
    padding:18px 22px;
    background: var(--surface-2);
    border-bottom:1px solid rgba(255,255,255,.10);
    text-align:center;
  }
  .brand{ font-weight:900; letter-spacing:.3px; margin:0; color:var(--brand-300); }
  .cardx-body{ padding:22px; }

  label{ font-weight:700; color:var(--text-strong); }
  .hint{ color:var(--ink-muted); font-size:.92rem; }

  .form-control{
    border:1px solid rgba(255,255,255,.12);
    border-radius:12px;
    background:var(--surface-3);
    color:var(--ink);
    font-weight:600;
    padding:.6rem .9rem;
  }
  .form-control:focus{
    border-color:#1e6acc;
    box-shadow:0 0 0 .2rem rgba(58,163,255,.25);
    background:var(--surface-3);
    color:var(--ink);
  }

  /* ปุ่มตาแสดง/ซ่อนรหัสผ่าน */
  .input-group-append .btn-eye{
    border:1px solid rgba(255,255,255,.12);
    border-left:0;
    background:var(--surface-3);
    color:#cfe2ff;
    border-top-right-radius:12px;
    border-bottom-right-radius:12px;
  }
  .input-group .form-control{
    border-top-right-radius:0;
    border-bottom-right-radius:0;
    border-right:0;
  }
  .btn-eye:focus{
    outline:none;
    box-shadow:0 0 0 .2rem rgba(58,163,255,.25);
  }

  .btn-primary{
    background: var(--brand-500);
    border:1px solid #1e6acc;
    color:#fff;
    font-weight:900;
    border-radius:12px;
    letter-spacing:.2px;
  }
  .btn-primary:hover{ filter:brightness(1.06) }

  .btn-secondary{
    background:#2b3442;
    border:1px solid rgba(255,255,255,.14);
    color:#e9eef6;
    font-weight:900;
    border-radius:12px;
  }
  .btn-secondary:hover{ filter:brightness(1.06) }

  .alert{ border-radius:12px; border:1px solid transparent; }
  .alert-danger{ background:#b3261e; color:#fff; border-color:#8b1d17; }

  :focus-visible{ outline:3px solid var(--brand-400); outline-offset:2px; border-radius:10px }
  *::-webkit-scrollbar{width:10px;height:10px}
  *::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
  *::-webkit-scrollbar-thumb:hover{background:#3a4752}
  *::-webkit-scrollbar-track{background:#151a20}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="cardx">
      <div class="cardx-header">
        <h3 class="brand">ตั้งรหัสผ่านใหม่</h3>
      </div>
      <div class="cardx-body">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
          <div class="form-group">
            <label for="username">ชื่อผู้ใช้</label>
            <input id="username" type="text" name="username" class="form-control" required autofocus />
          </div>

          <div class="form-group">
            <label for="password">รหัสผ่านใหม่</label>
            <div class="input-group">
              <input id="password" type="password" name="password" class="form-control" minlength="4" required />
              <div class="input-group-append">
                <button class="btn btn-eye" type="button" id="togglePass1" aria-label="แสดงรหัสผ่าน">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="password2">ยืนยันรหัสผ่านใหม่</label>
            <div class="input-group">
              <input id="password2" type="password" name="password2" class="form-control" minlength="4" required />
              <div class="input-group-append">
                <button class="btn btn-eye" type="button" id="togglePass2" aria-label="แสดงรหัสผ่าน">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <small class="hint">เพื่อความปลอดภัย รหัสผ่านจะถูกบันทึกแบบเข้ารหัส (bcrypt)</small>
          </div>

          <button type="submit" class="btn btn-primary btn-block mt-2">บันทึกรหัสผ่านใหม่</button>
          <a href="index.php" class="btn btn-secondary btn-block mt-2">กลับไปหน้าเข้าสู่ระบบ</a>
        </form>
      </div>
    </div>
  </div>

  <script>
    function hookToggle(idInput, idBtn){
      const input = document.getElementById(idInput);
      const btn   = document.getElementById(idBtn);
      btn?.addEventListener('click', ()=>{
        if(!input) return;
        const isPwd = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPwd ? 'text' : 'password');

        const icon = btn.querySelector('i');
        if (icon){
          icon.classList.toggle('bi-eye', !isPwd);       // ซ่อน → แสดง
          icon.classList.toggle('bi-eye-slash', isPwd);  // แสดง → ซ่อน
        }
        btn.setAttribute('aria-label', isPwd ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
      });
    }
    hookToggle('password','togglePass1');
    hookToggle('password2','togglePass2');

    // Enter ที่ช่องยืนยัน → submit
    document.getElementById('password2')?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ e.preventDefault(); e.target.closest('form')?.submit(); }
    });
  </script>
</body>
</html>

<?php
// forgot_password.php — Reset ด้วย username + ตั้งรหัสใหม่ (bcrypt)
session_start();
require __DIR__ . '/db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$error = '';
$done  = false;

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
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
  :root{
    --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
    --bg-grad1:#222831; --bg-grad2:#393E46;
    --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
    --ink:#F4F7F8; --ink-muted:#CFEAED;
    --brand-900:#EEEEEE; --brand-700:#BFC6CC; --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
    --danger:#e53935; --ring:#73E2E6; --shadow-lg:0 22px 66px rgba(0,0,0,.55); --shadow:0 14px 32px rgba(0,0,0,.42); --radius:18px;
  }
  html, body { height:100% }
  body{
    margin:0; font-family: 'Kanit', system-ui, -apple-system, "Segoe UI", Tahoma, Arial, sans-serif; color:var(--ink);
    background:
      radial-gradient(1000px 480px at 15% 10%, rgba(39,200,207,.18), transparent 60%),
      radial-gradient(900px 520px at 85% 35%, rgba(115,226,230,.10), transparent 55%),
      linear-gradient(135deg, var(--bg-grad1), var(--bg-grad2));
    display:flex; align-items:center; justify-content:center; padding:18px;
  }
  .wrap{ width:100%; max-width:500px; }
  .cardx{ background: color-mix(in oklab, var(--surface), white 6%); border:1px solid color-mix(in oklab, var(--brand-700), black 22%); color:var(--ink); border-radius:var(--radius); box-shadow:var(--shadow-lg); overflow:hidden; }
  .cardx-header{
    padding:18px 22px;
    background:
      radial-gradient(180px 60px at 95% -10%, rgba(115,226,230,.25), transparent 70%),
      linear-gradient(180deg, color-mix(in oklab, var(--surface-2), white 8%), var(--surface-3));
    border-bottom:1px solid color-mix(in oklab, var(--brand-700), black 22%); text-align:center;
  }
  .brand{ font-weight:900; letter-spacing:.3px; margin:0; color:var(--brand-300); text-shadow:0 1px 0 rgba(0,0,0,.3); }
  .cardx-body{ padding:22px; }

  label{ font-weight:700; color:var(--text-normal); }
  .form-control{
    border:2px solid color-mix(in oklab, var(--brand-700), white 30%); border-radius:12px; background:var(--brand-900);
    color:#0b1d2b; font-weight:600; padding:.6rem .9rem;
  }
  .form-control:focus{ border-color:var(--brand-400); box-shadow:0 0 0 .2rem color-mix(in oklab, var(--ring), white 35%); }
  .input-group-text{
    background:var(--surface-2); border:2px solid color-mix(in oklab, var(--brand-700), white 30%); border-left:0; color:var(--brand-300); cursor:pointer;
    border-top-right-radius:12px !important; border-bottom-right-radius:12px !important;
  }
  .input-group .form-control{ border-right:0; border-top-right-radius:0; border-bottom-right-radius:0; }

  .btn-primary{
    background: linear-gradient(180deg, var(--brand-500), var(--brand-400));
    border:1px solid color-mix(in oklab, var(--brand-500), black 25%); color:#062b33; font-weight:900; border-radius:12px; letter-spacing:.2px;
    box-shadow:0 10px 22px rgba(0,0,0,.25);
  }
  .btn-primary:hover{ filter:brightness(1.05) }
  .btn-secondary{
    background: linear-gradient(180deg, #9aa4ad, #7e8891);
    border:1px solid #56616a; color:#0e1419; font-weight:900; border-radius:12px; box-shadow:0 10px 22px rgba(0,0,0,.25);
  }

  .alert{ border-radius:12px; border:1px solid transparent; }
  .alert-danger{ background: color-mix(in oklab, var(--danger), black 10%); color:#fff; }
  .hint{ color:var(--text-muted); font-size:.92rem; }
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
                <span class="input-group-text" id="togglePass1" title="แสดง/ซ่อนรหัสผ่าน">👁️</span>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="password2">ยืนยันรหัสผ่านใหม่</label>
            <div class="input-group">
              <input id="password2" type="password" name="password2" class="form-control" minlength="4" required />
              <div class="input-group-append">
                <span class="input-group-text" id="togglePass2" title="แสดง/ซ่อนรหัสผ่าน">👁️</span>
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
      const i = document.getElementById(idInput);
      const b = document.getElementById(idBtn);
      b?.addEventListener('click', ()=>{
        if(!i) return;
        const isPwd = i.getAttribute('type') === 'password';
        i.setAttribute('type', isPwd ? 'text' : 'password');
        b.textContent = isPwd ? '🙈' : '👁️';
      });
    }
    hookToggle('password','togglePass1');
    hookToggle('password2','togglePass2');

    // Enter = submit
    document.getElementById('password2')?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ e.preventDefault(); e.target.closest('form')?.submit(); }
    });
  </script>
</body>
</html>

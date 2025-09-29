<?php
// index.php — Login hardened (supports bcrypt + md5 + plaintext, auto-migrate to bcrypt)
session_start();
require __DIR__ . '/db.php';

$error = '';

if (isset($_POST['login'])) {
    $username_raw = $_POST['username'] ?? '';
    $password_raw = $_POST['password'] ?? '';
    $username = trim($username_raw);
    $password = trim($password_raw);

    $sql = "SELECT user_id, username, student_ID, name, role, password
            FROM users
            WHERE username=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res  = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        $ok = false;
        if ($user) {
            $hash = (string)$user['password'];

            $is_bcrypt_or_argon = preg_match('/^\$(2y|2a|2b|argon2id|argon2i)\$/', $hash) === 1;
            $is_md5_hex         = preg_match('/^[a-f0-9]{32}$/i', $hash) === 1;

            if ($is_bcrypt_or_argon) {
                $ok = password_verify($password, $hash);
                if ($ok && password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost'=>11])) {
                    $new = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);
                    $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $upd->bind_param("si", $new, $user['user_id']);
                    $upd->execute(); $upd->close();
                }
            } elseif ($is_md5_hex) {
                $ok = (strcasecmp(md5($password), $hash) === 0);
                if ($ok) {
                    $new = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);
                    $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $upd->bind_param("si", $new, $user['user_id']);
                    $upd->execute(); $upd->close();
                }
            } else {
                $ok = hash_equals($hash, $password);
                if ($ok) {
                    $new = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);
                    $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $upd->bind_param("si", $new, $user['user_id']);
                    $upd->execute(); $upd->close();
                }
            }
        }

        if ($ok) {
            session_regenerate_id(true);
            $_SESSION['uid']        = (int)$user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['student_ID'] = $user['student_ID'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['role']       = $user['role'];

            header("Location: " . ($user['role'] === 'admin' ? "admin/adminmenu.php" : "SelectRole/role.php"));
            exit;
        } else {
            $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "Database Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>เข้าสู่ระบบ • PSU Blue Cafe</title>
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
    /* ===== โทนมินิมอลเข้มน้ำเงิน แบบเดียวกับหน้าร้าน ===== */
    --bg-grad1:#11161b; --bg-grad2:#141b22;
    --surface:#1a2230;  --surface-2:#192231; --surface-3:#202a3a;
    --ink:#e9eef6; --ink-muted:#b9c6d6; --text-strong:#ffffff;

    --brand-500:#3aa3ff; --brand-400:#7cbcfd; --brand-300:#a9cffd;

    --text-normal:#E6EBEE; --text-muted:#B9C2C9;
    --brand-700:#BFC6CC; --brand-900:#EEEEEE;
    --danger:#e53935; --ring:#73E2E6;

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

  .login-wrap{ width:100%; max-width:480px; }
  .cardx{
    background: var(--surface);
    border:1px solid rgba(255,255,255,.08);
    color:var(--ink);
    border-radius:var(--radius);
    box-shadow: none;
    overflow:hidden;
  }
  .cardx-header{
    padding:18px 22px;
    background: var(--surface-2);
    border-bottom:1px solid rgba(255,255,255,.10);
    display:flex; align-items:center; justify-content:center;
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

  .alert{ border-radius:12px; border:1px solid transparent; }
  .alert-danger{ background: #b3261e; color:#fff; border-color:#8b1d17; }
  .alert-success{ background: #1b5e20; color:#eaffea; border-color:#134718; }

  :focus-visible{ outline:3px solid var(--brand-400); outline-offset:2px; border-radius:10px }
  *::-webkit-scrollbar{width:10px;height:10px}
  *::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
  *::-webkit-scrollbar-thumb:hover{background:#3a4752}
  *::-webkit-scrollbar-track{background:#151a20}
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="cardx">
      <div class="cardx-header">
        <h3 class="brand">PSU Blue Cafe</h3>
      </div>
      <div class="cardx-body">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg']==='loggedout'): ?>
          <div class="alert alert-success mb-3">ออกจากระบบเรียบร้อยแล้ว</div>
        <?php endif; ?>
        <?php if (isset($_GET['msg']) && $_GET['msg']==='reset_ok'): ?>
          <div class="alert alert-success mb-3">ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว กรุณาเข้าสู่ระบบ</div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
          <div class="form-group">
            <label for="username">ชื่อผู้ใช้</label>
            <input id="username" type="text" name="username" class="form-control" required autofocus />
          </div>

          <div class="form-group">
            <label for="password">รหัสผ่าน</label>
            <div class="input-group">
              <input id="password" type="password" name="password" class="form-control" required />
              <div class="input-group-append">
                <button class="btn btn-eye" type="button" id="togglePass" aria-label="แสดง/ซ่อนรหัสผ่าน">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <small class="hint">กด <strong>Enter</strong> เพื่อเข้าสู่ระบบได้ทันที</small>
          </div>

          <button type="submit" name="login" value="1" class="btn btn-primary btn-block mt-3">
            เข้าสู่ระบบ
          </button>

          <div class="text-center mt-3">
            <a href="forgot_password.php" style="color:#a9cffd;font-weight:700">ลืมรหัสผ่าน?</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Submit ด้วย Enter ที่ช่องรหัสผ่าน
    document.getElementById('password')?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ e.preventDefault(); e.target.closest('form')?.submit(); }
    });

    // Toggle show/hide password ด้วย Bootstrap Icons
    const toggle = document.getElementById('togglePass');
    const pwd    = document.getElementById('password');
    toggle?.addEventListener('click', ()=>{
      if (!pwd) return;
      const isPwd = pwd.getAttribute('type') === 'password';
      pwd.setAttribute('type', isPwd ? 'text' : 'password');

      const icon = toggle.querySelector('i');
      if (icon){
        icon.classList.toggle('bi-eye', !isPwd);       // ซ่อน → แสดง
        icon.classList.toggle('bi-eye-slash', isPwd);  // แสดง → ซ่อน
      }
      // อัปเดต aria-label เพื่อการเข้าถึงที่ดีขึ้น
      toggle.setAttribute('aria-label', isPwd ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
    });
  </script>
</body>
</html>

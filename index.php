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

    // ดึงผู้ใช้ด้วย username แถวเดียว (อย่าฟิลเตอร์ด้วย password ใน SQL)
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

            // ตรวจรูปแบบ hash
            $is_bcrypt_or_argon = preg_match('/^\$(2y|2a|2b|argon2id|argon2i)\$/', $hash) === 1;
            $is_md5_hex         = preg_match('/^[a-f0-9]{32}$/i', $hash) === 1;

            if ($is_bcrypt_or_argon) {
                // bcrypt/argon
                $ok = password_verify($password, $hash);
                // อัปเดตเป็นค่าใหม่ถ้าจำเป็น (เช่น cost เปลี่ยน)
                if ($ok && password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost'=>11])) {
                    $new = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);
                    $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $upd->bind_param("si", $new, $user['user_id']);
                    $upd->execute(); $upd->close();
                }
            } elseif ($is_md5_hex) {
                // MD5 เดิม
                $ok = (strcasecmp(md5($password), $hash) === 0);
                // ถ้าถูก ให้ migrate เป็น bcrypt
                if ($ok) {
                    $new = password_hash($password, PASSWORD_BCRYPT, ['cost'=>11]);
                    $upd = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
                    $upd->bind_param("si", $new, $user['user_id']);
                    $upd->execute(); $upd->close();
                }
            } else {
                // plaintext เผลอบันทึกดิบ
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
  .login-wrap{ width:100%; max-width:460px; }
  .cardx{ background: color-mix(in oklab, var(--surface), white 6%); border:1px solid color-mix(in oklab, var(--brand-700), black 22%); color:var(--ink); border-radius:var(--radius); box-shadow:var(--shadow-lg); overflow:hidden; }
  .cardx-header{
    padding:18px 22px;
    background:
      radial-gradient(180px 60px at 95% -10%, rgba(115,226,230,.25), transparent 70%),
      linear-gradient(180deg, color-mix(in oklab, var(--surface-2), white 8%), var(--surface-3));
    border-bottom:1px solid color-mix(in oklab, var(--brand-700), black 22%); display:flex; align-items:center; justify-content:center;
  }
  .brand{ font-weight:900; letter-spacing:.3px; margin:0; color:var(--brand-300); text-shadow:0 1px 0 rgba(0,0,0,.3); }
  .cardx-body{ padding:22px; }
  label{ font-weight:700; color:var(--text-normal); }
  .hint{ color:var(--text-muted); font-size:.92rem; }
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
  .btn-danger{
    background: linear-gradient(180deg, #ff6b6b, #e94444); border:1px solid #c22f2f; color:#2a0202; font-weight:900; border-radius:12px; box-shadow:0 10px 22px rgba(0,0,0,.25);
  }
  .alert{ border-radius:12px; border:1px solid transparent; }
  .alert-danger{ background: color-mix(in oklab, var(--danger), black 10%); color:#fff; }
  .alert-success{ background: color-mix(in oklab, #2ecc71, black 10%); color:#fff; }
  :focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }
  *::-webkit-scrollbar{width:10px;height:10px} *::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px} *::-webkit-scrollbar-thumb:hover{background:#3a4752} *::-webkit-scrollbar-track{background:#151a20}
  </style>
</head>
<body>
  <div class="login-wrap">
    <div class="cardx">
      <div class="cardx-header"><h3 class="brand">PSU Blue Cafe</h3></div>
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
                <span class="input-group-text" id="togglePass" title="แสดง/ซ่อนรหัสผ่าน">👁️</span>
              </div>
            </div>
            <small class="hint">กด <strong>Enter</strong> เพื่อเข้าสู่ระบบได้ทันที</small>
          </div>

          <button type="submit" name="login" value="1" class="btn btn-primary btn-block mt-3">เข้าสู่ระบบ</button>
          <a href="logout.php" class="btn btn-danger btn-block mt-2">ออกจากระบบ</a>

          <div class="text-center mt-3">
            <a href="forgot_password.php" style="color:#73E2E6;font-weight:700">ลืมรหัสผ่าน?</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('password')?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){ e.preventDefault(); e.target.closest('form')?.submit(); }
    });
    const toggle = document.getElementById('togglePass');
    const pwd    = document.getElementById('password');
    toggle?.addEventListener('click', ()=>{
      if (!pwd) return;
      const isPwd = pwd.getAttribute('type') === 'password';
      pwd.setAttribute('type', isPwd ? 'text' : 'password');
      toggle.textContent = isPwd ? '🙈' : '👁️';
    });
  </script>
</body>
</html>

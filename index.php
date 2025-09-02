<?php
// index.php (MD5 version + role)
session_start();
require __DIR__ . '/db.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password_md5 = md5(trim($_POST['password'] ?? ''));

    $sql = "SELECT user_id, username, student_ID, name, role
            FROM users
            WHERE username=? AND password=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $username, $password_md5);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = $res->fetch_assoc();
        $stmt->close();

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['uid']        = (int)$user['user_id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['student_ID'] = $user['student_ID'];
            $_SESSION['name']       = $user['name'];
            $_SESSION['role']       = $user['role'];

            // ถ้าเป็น admin → ไปหน้าแอดมินทันที, ไม่งั้นไปหน้าเลือกสิทธิ์
            if ($user['role'] === 'admin') {
                header("Location: admin/adminmenu.php");
            } else {
                header("Location: SelectRole/role.php");
            }
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
<!-- ใส่ใน <head> -->
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600;700&display=swap" rel="stylesheet">

<style>


  
    :root{
      --psu-deep-blue:    #0D4071;  /* PSU Deep Blue */
      --psu-ocean-blue:   #4173BD;  /* PSU Ocean Blue */
      --psu-andaman-blue: #0094B3;  /* PSU Andaman Blue */
      --psu-sky-blue:     #29ABE2;  /* PSU Sky Blue */
      --psu-river-blue:   #4EC5E0;  /* PSU River Blue */
      --psu-sritrang:     #BBB4D8;  /* PSU Sritrang */

      --ink:#0b2746;
      --shadow: 0 14px 38px rgba(0,0,0,.30);
      --ring: rgba(41,171,226,.45);
    }

    html, body { height:100%; }
    body{
      font-family: 'Kanit', sans-serif;
      margin:0;
      display:flex; align-items:center; justify-content:center;
      background:
        radial-gradient(1200px 600px at 10% 10%, rgba(78,197,224,.18), transparent 60%),
        radial-gradient(900px 500px at 90% 40%, rgba(65,115,189,.20), transparent 55%),
        linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue));
      color:#fff;
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-wrap{
      width:100%; max-width: 460px; padding: 16px;
    }

    .cardx{
      background: linear-gradient(180deg, rgba(255,255,255,.12), rgba(255,255,255,.06));
      border:1px solid rgba(187,180,216,.35);
      color: #fff;
      border-radius: 18px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(6px);
      overflow:hidden;
    }

    .cardx-header{
      padding:18px 22px;
      background: linear-gradient(180deg, rgba(41,171,226,.25), rgba(13,64,113,.35));
      border-bottom: 1px solid rgba(187,180,216,.35);
      display:flex; align-items:center; justify-content:space-between;
    }
    .brand{
      font-weight:900; letter-spacing:.3px; margin:0;
    }
    .badge-psu{
      background: #fff; color: var(--psu-deep-blue);
      font-weight:900; border-radius:999px; padding:.25rem .6rem;
      border:1px solid #e6edff;
    }

    .cardx-body{ padding: 22px; }

    label{ font-weight:700; color:#eaf2ff; }
    .form-control{
      border:2px solid var(--psu-ocean-blue);
      border-radius:12px;
      background:#fff; color:#111;
      font-weight:600;
      padding:.6rem .9rem;
    }
    .form-control:focus{
      border-color: var(--psu-sky-blue);
      box-shadow: 0 0 0 .2rem var(--ring);
    }

    .btn-primary{
      background: linear-gradient(180deg, var(--psu-andaman-blue), var(--psu-sky-blue));
      border:1px solid #063d63; color:#002b4a;
      font-weight:900; border-radius:12px; letter-spacing:.2px;
    }
    .btn-primary:hover{
      background: linear-gradient(180deg, var(--psu-sky-blue), var(--psu-river-blue));
      color:#002a48;
    }

    .btn-danger{
      background: transparent; color:#fff; border:1px solid rgba(255,255,255,.65);
      font-weight:800; border-radius:12px;
    }
    .btn-danger:hover{
      background: rgba(255,255,255,.1);
      border-color:#fff;
    }

    .alert{
      border-radius:12px;
      border:1px solid rgba(255,255,255,.35);
    }
    .alert-danger{
      background: rgba(217,83,79,.88);
      color:#fff; border:none;
    }
    .alert-success{
      background: rgba(46,125,50,.92);
      color:#fff; border:none;
    }

    .hint{
      color:#d8e9ff; opacity:.9; font-size:.92rem;
    }
  </style>
</head>

<body>
<div class="login-wrap">
  <div class="cardx">
    <div class="cardx-header d-flex justify-content-center align-items-center">
      <h3 class="brand">PSU Blue Cafe</h3>  
    </div>


      <div class="cardx-body">
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger mb-3"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['msg']) && $_GET['msg']==='loggedout'): ?>
          <div class="alert alert-success mb-3">
            ออกจากระบบเรียบร้อยแล้ว
          </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off" novalidate>
          <div class="form-group">
            <label for="username">ชื่อผู้ใช้</label>
            <input id="username" type="text" name="username" class="form-control" required autofocus />
          </div>
          <div class="form-group">
            <label for="password">รหัสผ่าน</label>
            <input id="password" type="password" name="password" class="form-control" required />
            <small class="hint">กด <strong>Enter</strong> เพื่อเข้าสู่ระบบได้ทันที</small>
          </div>

          <button type="submit" name="login" value="1" class="btn btn-primary btn-block mt-3">
            เข้าสู่ระบบ
          </button>
          <a href="logout.php" class="btn btn-danger btn-block mt-2">ออกจากระบบ</a>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Enter on password = submit
    document.getElementById('password')?.addEventListener('keydown', e=>{
      if(e.key === 'Enter'){
        e.target.closest('form')?.submit();
      }
    });
  </script>
</body>
</html>

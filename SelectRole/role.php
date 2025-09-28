<?php
session_start();
if (empty($_SESSION['uid'])) {
  header("Location: ../index.php");
  exit();
}
$username = $_SESSION['username'] ?? '';
$name     = $_SESSION['name'] ?? '';
$display  = $username ?: $name;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เลือกสิทธิการใช้งาน • PSU Blue Cafe</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    /* ===== Teal-Graphite Theme ===== */
    :root{
      --text-strong:#F4F7F8;
      --text-normal:#E6EBEE;
      --text-muted:#B9C2C9;

      --bg-grad1:#222831;        /* page background */
      --bg-grad2:#393E46;

      --surface:#1C2228;         /* cards */
      --surface-2:#232A31;
      --surface-3:#2B323A;

      --ink:#F4F7F8;
      --ink-muted:#CFEAED;

      --brand-900:#EEEEEE;
      --brand-700:#BFC6CC;
      --brand-500:#00ADB5;       /* accent */
      --brand-400:#27C8CF;
      --brand-300:#73E2E6;

      --danger:#e53935;

      --ring:#73E2E6;
      --radius:16px;
      --shadow-lg:0 22px 66px rgba(0,0,0,.55);
      --shadow:   0 14px 32px rgba(0,0,0,.42);
    }

    html,body{height:100%}
    body{
      margin:0; color:var(--ink);
      font-family: "Segoe UI", Tahoma, Arial, sans-serif;
      background:
        radial-gradient(1000px 420px at 90% -10%, rgba(115,226,230,.20), transparent 60%),
        radial-gradient(900px 520px at 10% 30%, rgba(0,173,181,.18), transparent 55%),
        linear-gradient(135deg, var(--bg-grad1), var(--bg-grad2));
      display:flex; align-items:center; justify-content:center;
      padding:18px;
    }

    .container-role{ width:100%; max-width:980px; }

    /* Header chip */
    .top-chip{
      display:flex; align-items:center; gap:10px;
      background: color-mix(in oklab, var(--surface), white 8%);
      border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
      border-radius:999px; padding:10px 14px; margin-bottom:18px;
      box-shadow:0 8px 20px rgba(0,0,0,.18)
    }
    .top-chip .brand{ font-weight:900; color:var(--brand-300); letter-spacing:.3px }
    .top-chip .user{ color:var(--text-normal); font-weight:700 }
    .top-chip .sp{ flex:1 }
    .top-chip .btn{ border-radius:12px; font-weight:800 }

    /* Title */
    h3{
      text-align:center; margin-bottom:18px; font-weight:900;
      color:var(--brand-300); text-shadow:0 2px 6px rgba(0,0,0,.25);
    }

    /* Card as full clickable tile */
    .tile{
      position:relative; height:100%;
      background: var(--surface);
      border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
      border-radius: var(--radius);
      padding:28px 18px;
      text-align:center; text-decoration:none !important; color:var(--ink);
      box-shadow: var(--shadow);
      transition: transform .12s ease, box-shadow .15s ease, border-color .15s ease, filter .12s ease;
      display:flex; flex-direction:column; align-items:center; justify-content:center;
    }
    .tile:hover{
      transform: translateY(-4px);
      border-color: color-mix(in oklab, var(--brand-400), black 10%);
      box-shadow:0 16px 32px rgba(0,0,0,.35);
      filter: brightness(1.02);
    }
    .tile:focus-visible{
      outline:4px solid color-mix(in oklab, var(--ring), white 20%);
      outline-offset:3px;
    }
    .tile img{
      width:88px; height:88px; object-fit:contain; margin-bottom:12px;
      filter: drop-shadow(0 6px 10px rgba(0,0,0,.28));
    }
    .tile h5{
      margin:0; font-weight:900; color:var(--text-strong); letter-spacing:.3px;
    }
    .tile .tag{
      margin-top:8px; font-size:.9rem; color:var(--text-muted);
      background: color-mix(in oklab, var(--surface-2), white 6%);
      border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
      padding:6px 10px; border-radius:999px; font-weight:800;
    }

    /* Column spacing on mobile */
    .row.gx-12{ margin-left:-6px; margin-right:-6px }
    .row.gx-12 > [class^="col"]{ padding-left:6px; padding-right:6px }

    /* Danger/Logout */
    .btn-danger{
      background: linear-gradient(180deg, #ff6b6b, #e94444);
      border:1px solid #c22f2f; color:#220202;
    }
    .btn-danger:hover{ filter:brightness(1.05) }

    /* Small utils */
    .muted{ color:var(--text-muted) }
  </style>
</head>
<body>
  <div class="container container-role">
    <!-- Top chip -->
    <div class="top-chip">
      <div class="brand">PSU Blue Cafe</div>
      <span class="muted">•</span>
      <div class="user">ผู้ใช้: <strong><?= htmlspecialchars($display, ENT_QUOTES, 'UTF-8') ?></strong></div>
      <div class="sp"></div>
      <a href="../logout.php" class="btn btn-sm btn-danger">ออกจากระบบ</a>
    </div>

    <h3>เลือกสิทธิการใช้งาน</h3>

    <div class="row gx-12">
      <div class="col-md-4 mb-3">
        <a class="tile" href="../front_store/front_store.php" aria-label="ไปที่หน้าร้าน">
          <img src="icons/store.png" alt="หน้าร้าน">
          <h5>หน้าร้าน</h5>
          <div class="tag">สั่ง–คิดเงิน</div>
        </a>
      </div>

      <div class="col-md-4 mb-3">
        <a class="tile" href="../back_store/back_store.php" aria-label="ไปที่หลังร้าน">
          <img src="icons/coffee-machine.png" alt="หลังร้าน">
          <h5>หลังร้าน</h5>
          <div class="tag">จัดเตรียม/อัปเดตสถานะ</div>
        </a>
      </div>

      <div class="col-md-4 mb-3">
        <a class="tile" href="../attendance/check_in.php" aria-label="ไปที่ลงเวลาทำงาน">
          <img src="icons/register.png" alt="ลงเวลา">
          <h5>ลงเวลาทำงาน</h5>
          <div class="tag">เช็คอิน/เอาท์</div>
        </a>
      </div>
    </div>
  </div>
</body>
</html>

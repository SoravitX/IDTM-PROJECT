<?php
session_start();
// ถ้ายังไม่ล็อกอิน redirect กลับหน้า login
if (empty($_SESSION['uid'])) {
    header("Location: ../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <title>เลือกสิทธิการใช้งาน</title>
  <link rel="stylesheet"
        href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <style>
    :root {
      --psu-deep-blue:    #0D4071;  
      --psu-ocean-blue:   #4173BD;  
      --psu-andaman-blue: #0094B3;  
      --psu-sky-blue:     #29ABE2;  
      --psu-river-blue:   #4EC5E0;  
      --psu-sritrang:     #BBB4D8;  
    }

    body {
      min-height:100vh;
      margin:0;
      display:flex; align-items:center; justify-content:center;
      background: linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue));
      font-family: "Segoe UI", Tahoma, sans-serif;
      color:#fff;
    }

    h3 { 
      text-align:center; 
      margin-bottom:32px; 
      font-weight:900;
      color: var(--psu-river-blue); 
      text-shadow:0 2px 6px rgba(0,0,0,.25);
    }

    .container-role { max-width:900px; }

    .card-option {
      background: rgba(255,255,255,.1);
      border: 2px solid var(--psu-sritrang);
      border-radius:18px;
      padding:28px 20px;
      text-align:center;
      transition:.25s;
      height:100%;
      box-shadow:0 8px 20px rgba(0,0,0,.2);
    }
    .card-option:hover {
      transform:translateY(-6px) scale(1.02);
      background: linear-gradient(180deg, var(--psu-sky-blue), var(--psu-river-blue));
      border-color: var(--psu-andaman-blue);
      cursor:pointer;
      color:#0D4071;
    }
    .card-option img {
      width:95px; height:95px; object-fit:contain; margin-bottom:14px;
      filter: drop-shadow(0 4px 6px rgba(0,0,0,.3));
    }
    .card-option h5 { 
      color:#fff; font-weight:700; letter-spacing:.5px;
    }

    .card-option:hover h5 {
      color:#0D4071;
    }

    a.text-dark { color:#fff !important; text-decoration:none; }
  </style>
</head>
<body>
  <div class="container container-role">
    <h3>เลือกสิทธิการใช้งาน</h3>
    <div class="row">
      <div class="col-md-6 mb-4">
        <a href="../front_store/front_store.php" class="text-dark">
          <div class="card-option">
            <img src="icons/store.png" alt="หน้าร้าน">
            <h5>หน้าร้าน</h5> 
          </div>
        </a> 
      </div>
      <div class="col-md-6 mb-4">
        <a href="../back_store/back_store.php" class="text-dark">
          <div class="card-option">
            <img src="icons/coffee-machine.png" alt="หลังร้าน">
            <h5>หลังร้าน</h5>
          </div>
        </a>
      </div>
      <div class="col-md-6 mb-4">
        <a href="../attendance/check_in.php" class="text-dark">
          <div class="card-option">
            <img src="icons/register.png" alt="ลงเวลา">
            <h5>ลงเวลาทำงาน</h5>
          </div>
        </a>
      </div>
      <div class="col-md-6 mb-4">
        <a href="../admin/adminmenu.php" class="text-dark">
          <div class="card-option">
            <img src="icons/admin.png" alt="ผู้ดูแลระบบ">
            <h5>ผู้ดูแลระบบ</h5>
          </div>
        </a>
      </div>
    </div>
  </div>
</body>
</html>

<?php
// admin/add_category.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $name = trim($_POST['category_name'] ?? '');
  if ($name==='') {
    $msg = 'กรุณากรอกชื่อหมวดหมู่';
  } else {
    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) { header("Location: adminmenu.php?msg=added_cat"); exit; }
    $msg = 'บันทึกไม่สำเร็จ';
  }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เพิ่มหมวดหมู่ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{ --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-sritrang:#BBB4D8; }
body{background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean)); color:#fff; font-family:"Segoe UI",Tahoma;}
.wrap{max-width:560px;margin:40px auto;padding:0 16px}
.cardx{background:#fff;border:1px solid var(--psu-sritrang);border-radius:14px;color:#0D4071;box-shadow:0 10px 24px rgba(0,0,0,.15);padding:20px;}
.btn-ghost{background:#0094B3;border:1px solid #063d63;color:#fff;font-weight:700;border-radius:999px}
</style>
</head>
<body>
<div class="wrap">
  <div class="cardx">
    <h4 class="mb-3 font-weight-bold">เพิ่มหมวดหมู่</h4>

    <?php if($msg): ?>
      <div class="alert alert-<?= $ok?'success':'danger' ?>"><?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>ชื่อหมวดหมู่</label>
        <input type="text" name="category_name" class="form-control" required autofocus>
      </div>
      <div class="d-flex">
        <button class="btn btn-ghost mr-2" type="submit">บันทึก</button>
        <a href="adminmenu.php" class="btn btn-outline-secondary">ย้อนกลับ</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>

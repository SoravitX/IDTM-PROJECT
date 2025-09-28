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
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
/* ========= Teal–Graphite Dark ========= */
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg-grad1:#222831; --bg-grad2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --ink:#F4F7F8; --ink-muted:#CFEAED;
  --brand-900:#EEEEEE; --brand-700:#BFC6CC;
  --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
  --ok:#22c55e; --danger:#ef4444; --ring:#27C8CF;
  --shadow-lg:0 22px 66px rgba(0,0,0,.55); --shadow:0 14px 32px rgba(0,0,0,.42);
}
html,body{height:100%}
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--ink); font-family:"Segoe UI",Tahoma,Arial,sans-serif; margin:0;
}
.wrap{max-width:640px;margin:40px auto;padding:0 16px}
.topbar{
  display:flex;align-items:center;justify-content:space-between;
  background:color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:14px; padding:12px 16px; box-shadow:0 8px 20px rgba(0,0,0,.35);
  margin-bottom:14px;
}
.brand{margin:0;font-weight:900;letter-spacing:.3px;color:var(--text-strong)}
.cardx{
  background:color-mix(in oklab, var(--surface), white 8%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:16px; color:var(--text-normal);
  box-shadow:var(--shadow); padding:20px;
}
label{font-weight:800;color:var(--brand-900)}
.form-control{
  background:var(--surface-2); border:1px solid color-mix(in oklab, var(--brand-700), black 24%);
  color:var(--ink); border-radius:10px;
}
.form-control::placeholder{color:#9fb0ba}
.form-control:focus{box-shadow:0 0 0 .2rem color-mix(in oklab,var(--ring),white 25%); border-color:var(--brand-400)}
.btn-ghost{
  background:linear-gradient(180deg,var(--brand-500),var(--brand-400));
  color:#062b33; font-weight:900; border:0; border-radius:12px; box-shadow:0 8px 18px rgba(0,0,0,.25)
}
.btn-ghost:active{transform:translateY(1px)}
.btn-back{border-radius:12px;border:1px solid color-mix(in oklab, var(--brand-700), black 24%); color:var(--brand-900)}
.alert{
  border-radius:12px; border:1px solid transparent;
}
.alert-success{background:color-mix(in oklab, var(--ok), black 82%); border-color:color-mix(in oklab, var(--ok), black 50%); color:#eaffef}
.alert-danger{background:color-mix(in oklab, var(--danger), black 82%); border-color:color-mix(in oklab, var(--danger), black 50%); color:#ffecec}
.small-hint{color:var(--text-muted)}
:focus-visible{outline:3px solid var(--ring); outline-offset:2px; border-radius:10px}
/* scrollbar */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#3a4752}
*::-webkit-scrollbar-track{background:#151a20}
</style>
</head>
<body>
<div class="wrap">

  <div class="topbar">
    <h5 class="brand"><i class="bi bi-tags"></i> เพิ่มหมวดหมู่ • PSU Blue Cafe</h5>
    <a href="adminmenu.php" class="btn btn-sm btn-back">
      <i class="bi bi-arrow-left-circle"></i> กลับหน้าจัดการเมนู
    </a>
  </div>

  <div class="cardx">
    <h4 class="mb-2 font-weight-bold"><i class="bi bi-plus-circle"></i> เพิ่มหมวดหมู่ใหม่</h4>
    <div class="small small-hint mb-3"><i class="bi bi-info-circle"></i> ใส่ชื่อหมวดหมู่ให้ชัดเจน เช่น “เครื่องดื่มร้อน”, “ของหวาน”</div>

    <?php if($msg): ?>
      <div class="alert alert-<?= $ok?'success':'danger' ?> mb-3" role="alert">
        <?= htmlspecialchars($msg,ENT_QUOTES,'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="form-group">
        <label for="category_name"><i class="bi bi-tag"></i> ชื่อหมวดหมู่</label>
        <input type="text" name="category_name" id="category_name" class="form-control"
               placeholder="เช่น เครื่องดื่มเย็น" required autofocus>
      </div>

      <div class="d-flex align-items-center mt-3" style="gap:10px">
        <button class="btn btn-ghost" type="submit"><i class="bi bi-check2-circle"></i> บันทึก</button>
        <a href="adminmenu.php" class="btn btn-outline-light"><i class="bi bi-x-circle"></i> ยกเลิก</a>
      </div>
    </form>
  </div>
</div>
</body>
</html>

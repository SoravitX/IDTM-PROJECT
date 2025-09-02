<?php
// admin/add_menu.php  — PSU tone + image preview
declare(strict_types=1);

require __DIR__ . '/../db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);

    $target_dir = __DIR__ . "/images/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $message = "อัปโหลดไฟล์ไม่สำเร็จ";
    } else {
        $f = $_FILES['image'];
        $allowedExt = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $message = "รองรับเฉพาะไฟล์รูป: ".implode(', ',$allowedExt);
        } elseif ($f['size'] > 5*1024*1024) {
            $message = "ไฟล์ใหญ่เกินไป (สูงสุด 5MB)";
        } else {
            $imgInfo = @getimagesize($f['tmp_name']);
            if ($imgInfo === false) {
                $message = "ไฟล์ที่อัปโหลดไม่ใช่ภาพ";
            } else {
                $filename    = sprintf("%s_%s.%s", time(), bin2hex(random_bytes(5)), $ext);
                $target_file = $target_dir . $filename;
                if (!move_uploaded_file($f['tmp_name'], $target_file)) {
                    $message = "ไม่สามารถบันทึกรูปภาพได้";
                } else {
                    $stmt = $conn->prepare("INSERT INTO menu (name, price, image, category_id) VALUES (?,?,?,?)");
                    $stmt->bind_param("sdsi", $name, $price, $filename, $category_id);
                    if ($stmt->execute()) {
                        header("Location: adminmenu.php?msg=added");
                        exit;
                    }
                    $message = "บันทึกข้อมูลล้มเหลว: ".$stmt->error;
                    $stmt->close();
                }
            }
        }
    }
}
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เพิ่มเมนูใหม่ • PSU Blue Cafe (Admin)</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<style>
/* ===== PSU Theme ===== */
:root{
  --psu-deep:#0D4071;      /* Deep Blue */
  --psu-ocean:#4173BD;     /* Ocean Blue */
  --psu-sky:#29ABE2;       /* Sky Blue */
  --psu-river:#4EC5E0;     /* River Blue */
  --psu-sritrang:#BBB4D8;  /* Sritrang */
  --ink:#0b2746;
  --ring:#7dd3fc;
}
html,body{height:100%}
body{
  margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif; color:#fff;
  background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean));
  display:flex; align-items:flex-start; justify-content:center; padding:24px;
}

/* Topbar chip */
.header-chip{
  display:inline-flex; align-items:center; gap:10px;
  padding:10px 14px; border-radius:12px;
  background:rgba(255,255,255,.10); border:1px solid rgba(187,180,216,.35);
  backdrop-filter: blur(6px);
  box-shadow:0 8px 20px rgba(0,0,0,.18);
}

/* Card */
.card-psu{
  width:min(720px,95vw);
  margin-top:14px;
  background:#ffffff;
  color:var(--ink);
  border:1px solid #e3ebff;
  border-radius:18px;
  box-shadow:0 20px 60px rgba(0,0,0,.35);
  overflow:hidden;
}
.card-psu .head{
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 18px;
  background:
    radial-gradient(200px 70px at 90% -20%, rgba(41,171,226,.30), transparent 70%),
    linear-gradient(180deg,#f7fbff,#eef6ff);
  border-bottom:1px solid #dbe7ff;
}
.card-psu h3{ margin:0; font-weight:900; color:var(--psu-deep) }
.card-psu .body{ padding:18px; }
.card-psu .footer{
  padding:14px 18px; background:#f7faff; border-top:1px solid #e3ebff;
}

/* Form controls */
label{ font-weight:800; color:#13406e }
.form-control, .custom-file-input, .custom-select{
  border:2px solid #cfe0ff; border-radius:12px;
}
.form-control:focus, .custom-select:focus{
  border-color:#92c5ff;
  box-shadow:0 0 0 .18rem rgba(41,171,226,.25);
}
.helper{ color:#4b6b93; font-size:.85rem }

/* Buttons */
.btn-psu{
  font-weight:900; letter-spacing:.2px; border-radius:999px; padding:.65rem 1.2rem;
  border:1px solid #0a3970;
  background:linear-gradient(180deg,#3aa3ff,#1f7ee8);
  color:#fff;
}
.btn-psu:hover{ filter:brightness(1.05) }

.btn-ghost{
  font-weight:800; border-radius:999px; padding:.55rem 1rem;
  background:var(--psu-sky); border:1px solid #0a3e62; color:#002a48;
}
.btn-outline{
  border-radius:999px; padding:.55rem 1rem; font-weight:800;
  border:2px solid #cfe0ff; color:#0D4071; background:#fff;
}
.alert-psu{
  background:#e53935; color:#fff; border:none; border-radius:10px; font-weight:800;
}

/* Image preview box */
.preview{
  display:flex; align-items:center; gap:12px; margin-top:10px;
}
.preview img{
  width:120px; height:120px; object-fit:cover; border-radius:12px;
  border:1px solid #e3ebff; background:#eef5ff;
}

/* focus ring for a11y */
:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }
</style>
</head>
<body>

<div style="width:min(720px,95vw)">
  <div class="header-chip">
    <strong>PSU Blue Cafe • Admin</strong>
    <span class="text-white-50">เพิ่มเมนูใหม่</span>
    <a href="adminmenu.php" class="btn btn-outline btn-sm ml-auto">กลับหน้าจัดการเมนู</a>
  </div>

  <div class="card-psu">
    <div class="head">
      <h3>เพิ่มเมนูใหม่</h3>
      <a href="adminmenu.php" class="btn btn-ghost btn-sm">ย้อนกลับ</a>
    </div>

    <div class="body">
      <?php if (!empty($message)): ?>
        <div class="alert alert-psu mb-3"><?= htmlspecialchars($message,ENT_QUOTES,'UTF-8') ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-group">
          <label for="name">ชื่อเมนู</label>
          <input type="text" id="name" name="name" class="form-control" required autofocus>
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="price">ราคา (บาท)</label>
            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="form-group col-md-6">
            <label for="category_id">หมวดหมู่</label>
            <select id="category_id" name="category_id" class="custom-select" required>
              <option value="">-- เลือกหมวดหมู่ --</option>
              <?php while($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$cat['category_id'] ?>">
                  <?= htmlspecialchars($cat['category_name'],ENT_QUOTES,'UTF-8') ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="image">รูปภาพ</label>
          <input type="file" id="image" name="image" class="form-control-file" accept="image/*" required>
          <div class="preview" id="preview" hidden>
            <img id="previewImg" alt="ตัวอย่างรูป">
            <div class="helper">ตัวอย่างรูปที่จะอัปโหลด</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div class="helper mb-2">ตรวจสอบชื่อ/ราคา/หมวดหมู่ และรูปก่อนบันทึก</div>
          <button type="submit" class="btn btn-psu">บันทึกเมนู</button>
        </div>
      </form>
    </div>

   
<script>
// พรีวิวรูป
const input = document.getElementById('image');
const previewBox = document.getElementById('preview');
const previewImg = document.getElementById('previewImg');
input.addEventListener('change', e=>{
  const file = e.target.files && e.target.files[0];
  if (!file) { previewBox.hidden = true; return; }
  const url = URL.createObjectURL(file);
  previewImg.src = url;
  previewBox.hidden = false;
});
</script>
</body>
</html>

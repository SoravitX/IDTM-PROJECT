<?php
// admin/add_menu.php — Dark-Teal UI + drag&drop preview (no logic change)
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
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg1:#222831; --bg2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --brand:#00ADB5; --brand-2:#27C8CF; --ok:#2ecc71; --danger:#e53935;
  --ring: rgba(0,173,181,.35);
  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:0 14px 32px rgba(0,0,0,.42);
}
html,body{height:100%}
body{
  margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
  background:
    radial-gradient(900px 360px at 105% -10%, rgba(39,200,207,.15), transparent 60%),
    linear-gradient(135deg,var(--bg1),var(--bg2));
  color:var(--text-strong); padding:28px 16px;
  display:flex; justify-content:center; align-items:flex-start;
}
.shell{ width:min(860px,96vw) }

/* header chip */
.header-chip{
  display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:12px;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid rgba(255,255,255,.14); box-shadow:var(--shadow);
}
.header-chip .btn-linkx{
  margin-left:auto; border:1px solid rgba(255,255,255,.18); border-radius:999px; padding:.4rem .8rem;
  color:var(--text-normal); text-decoration:none; background:color-mix(in oklab, var(--surface-3), white 8%);
}
.header-chip .btn-linkx:hover{ filter:brightness(1.05) }

/* card */
.cardx{
  margin-top:14px; border-radius:18px; overflow:hidden; box-shadow:var(--shadow-lg);
  background:linear-gradient(180deg,var(--surface),var(--surface-2)); border:1px solid rgba(255,255,255,.08);
}
.cardx .head{
  display:flex; align-items:center; justify-content:space-between; padding:16px 18px;
  background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
  border-bottom:1px solid rgba(255,255,255,.1);
}
.cardx h3{ margin:0; font-weight:900; color:#fff }
.cardx .body{ padding:18px }
.cardx .footer{ padding:14px 18px; background:var(--surface-3); border-top:1px solid rgba(255,255,255,.08) }

/* form */
label{ font-weight:800; color:var(--text-normal) }
.form-control,.custom-select{
  background:var(--surface-3); color:var(--text-strong);
  border:1.5px solid rgba(255,255,255,.12); border-radius:12px;
}
.form-control::placeholder{ color:#9aa3ab }
.form-control:focus,.custom-select:focus{
  border-color:var(--brand); box-shadow:0 0 0 .2rem var(--ring); background:#2c343c;
}
.helper{ color:var(--text-muted); font-size:.85rem }

/* buttons */
.btn-main{
  font-weight:900; border-radius:999px; padding:.65rem 1.2rem; border:0;
  background:linear-gradient(180deg,var(--brand),#07949B); color:#071619;
  box-shadow:0 12px 30px rgba(0,173,181,.28);
}
.btn-ghost{
  font-weight:800; border-radius:999px; padding:.5rem 1rem; border:0;
  background:color-mix(in oklab, var(--surface-2), white 6%); color:var(--text-strong);
  border:1px solid rgba(255,255,255,.16);
}
.alert-error{
  background:rgba(229,57,53,.14); color:#ffb4b1; border:1px solid rgba(229,57,53,.35);
  border-radius:12px; font-weight:800;
}

/* upload */
.drop{
  margin-top:6px; border:2px dashed rgba(255,255,255,.18); border-radius:14px;
  background:#1e262d; padding:14px; text-align:center; transition:border-color .15s, background .15s;
}
.drop.dragover{ border-color:var(--brand-2); background:#1b242b }
.drop small{ color:var(--text-muted) }
.preview{
  display:flex; align-items:center; gap:12px; margin-top:12px;
}
.preview img{
  width:140px; height:140px; object-fit:cover; border-radius:12px;
  border:1px solid rgba(255,255,255,.16); background:#12181f;
}
.badge-name{
  display:inline-flex; align-items:center; gap:6px; padding:.25rem .6rem; border-radius:999px;
  background:color-mix(in oklab, var(--surface-2), white 6%); border:1px solid rgba(255,255,255,.14);
  color:var(--text-normal); font-weight:800;
}
.btn-danger-soft{
  border-radius:999px; padding:.35rem .8rem; font-weight:800; border:1px solid rgba(229,57,53,.45);
  background:rgba(229,57,53,.15); color:#ffd6d5;
}
:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }
</style>
</head>
<body>
<div class="shell">

  <div class="header-chip">
    <strong>PSU Blue Cafe • Admin</strong>
    <span class="text-white-50">เพิ่มเมนูใหม่</span>
    <a href="adminmenu.php" class="btn-linkx">← กลับหน้าจัดการเมนู</a>
  </div>

  <div class="cardx">
    <div class="head">
      <h3>เพิ่มเมนูใหม่</h3>
      <a href="adminmenu.php" class="btn-ghost btn-sm">ย้อนกลับ</a>
    </div>

    <div class="body">
      <?php if (!empty($message)): ?>
        <div class="alert alert-error mb-3"><?= htmlspecialchars($message,ENT_QUOTES,'UTF-8') ?></div>
      <?php endif; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-group">
          <label for="name">ชื่อเมนู</label>
          <input type="text" id="name" name="name" class="form-control" required autofocus placeholder="เช่น ชาเขียวนม">
        </div>

        <div class="form-row">
          <div class="form-group col-md-6">
            <label for="price">ราคา (บาท)</label>
            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required placeholder="เช่น 40.00">
            <small id="priceHint" class="helper"></small>
          </div>
          <div class="form-group col-md-6">
            <label for="category_id">หมวดหมู่</label>
            <select id="category_id" name="category_id" class="custom-select" required>
              <option value="">— เลือกหมวดหมู่ —</option>
              <?php while($cat = $categories->fetch_assoc()): ?>
                <option value="<?= (int)$cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name'],ENT_QUOTES,'UTF-8') ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="image">รูปภาพเมนู</label>

          <!-- Drag & Drop zone -->
          <div id="drop" class="drop">
            <div><strong>ลาก-วาง</strong> ไฟล์รูปมาวางที่นี่ หรือ <u>คลิกเพื่อเลือกไฟล์</u></div>
            <small>รองรับ: JPG, PNG, GIF, WEBP (สูงสุด 5MB)</small>
            <input type="file" id="image" name="image" accept="image/*" required hidden>
          </div>

          <!-- Preview -->
          <div class="preview" id="preview" hidden>
            <img id="previewImg" alt="ตัวอย่างรูป">
            <div>
              <div class="badge-name" id="fileName">—</div>
              <div class="mt-2">
                <button type="button" id="removePreview" class="btn-danger-soft btn-sm">ลบรูปออก</button>
              </div>
              <small class="helper d-block mt-1">ตรวจสอบรูปก่อนบันทึก</small>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap">
          <div class="helper mb-2">ตรวจสอบชื่อ / ราคา / หมวดหมู่ และรูปก่อนกด “บันทึกเมนู”</div>
          <button type="submit" class="btn-main">บันทึกเมนู</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ---------- ราคา: โชว์ตัวอย่างรูปแบบ ----------
const price = document.getElementById('price');
const priceHint = document.getElementById('priceHint');
function fmt(n){ return (Number(n)||0).toLocaleString('th-TH',{minimumFractionDigits:2, maximumFractionDigits:2}); }
price.addEventListener('input', ()=> priceHint.textContent = 'ตัวอย่าง: ' + fmt(price.value) + ' บาท');
price.dispatchEvent(new Event('input'));

// ---------- อัปโหลดรูป: drag & drop + preview ----------
const drop = document.getElementById('drop');
const fileInput = document.getElementById('image');
const previewBox = document.getElementById('preview');
const previewImg = document.getElementById('previewImg');
const fileName = document.getElementById('fileName');
const removeBtn = document.getElementById('removePreview');

drop.addEventListener('click', ()=> fileInput.click());

['dragenter','dragover'].forEach(ev=>{
  drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); drop.classList.add('dragover'); });
});
['dragleave','drop'].forEach(ev=>{
  drop.addEventListener(ev, e=>{ e.preventDefault(); e.stopPropagation(); drop.classList.remove('dragover'); });
});
drop.addEventListener('drop', e=>{
  const f = e.dataTransfer.files && e.dataTransfer.files[0];
  if (f) { fileInput.files = e.dataTransfer.files; showPreview(f); }
});

fileInput.addEventListener('change', e=>{
  const f = e.target.files && e.target.files[0];
  if (f) showPreview(f);
});

function showPreview(file){
  const url = URL.createObjectURL(file);
  previewImg.src = url;
  fileName.textContent = file.name + ' • ' + Math.round(file.size/1024) + ' KB';
  previewBox.hidden = false;
}
removeBtn.addEventListener('click', ()=>{
  fileInput.value = ''; previewBox.hidden = true; previewImg.src=''; fileName.textContent='—';
});
</script>
</body>
</html>

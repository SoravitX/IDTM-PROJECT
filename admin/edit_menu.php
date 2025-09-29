<?php
// admin/edit_menu.php — Deep Blue theme + Preview / Drag&Drop / Validate
declare(strict_types=1);
include '../db.php';

// รับ id เมนูที่ต้องการแก้ไข
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: adminmenu.php'); exit; }

$message = '';
$msgClass = '';

// ดึงข้อมูลเมนู
$stmt = $conn->prepare("SELECT menu_id, name, price, image, category_id FROM menu WHERE menu_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();
if (!$menu) { header('Location: adminmenu.php'); exit; }

// ดึงหมวดหมู่ทั้งหมด
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);

    $filename = $menu['image']; // ใช้ไฟล์เดิมโดยค่าเริ่มต้น

    // โฟลเดอร์เก็บภาพ
    $target_dir_fs = __DIR__ . "/images/";
    if (!is_dir($target_dir_fs)) { mkdir($target_dir_fs, 0755, true); }

    // อัปโหลดรูปใหม่ (ถ้ามี)
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['image'];
        $allowedExt = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) {
            $message = "รองรับเฉพาะไฟล์รูป: " . implode(', ', $allowedExt);
            $msgClass = "danger";
        } elseif ($f['size'] > 5 * 1024 * 1024) {
            $message = "ไฟล์ใหญ่เกินไป (สูงสุด 5MB)";
            $msgClass = "danger";
        } elseif (@getimagesize($f['tmp_name']) === false) {
            $message = "ไฟล์ที่อัปโหลดไม่ใช่ภาพ";
            $msgClass = "danger";
        } else {
            // ตั้งชื่อใหม่กันซ้ำ
            $new_filename   = sprintf("%s_%s.%s", time(), bin2hex(random_bytes(5)), $ext);
            $target_file_fs = $target_dir_fs . $new_filename;

            if (move_uploaded_file($f['tmp_name'], $target_file_fs)) {
                // ลบไฟล์เดิม (ถ้ามี)
                if (!empty($filename) && file_exists($target_dir_fs . $filename)) {
                    @unlink($target_dir_fs . $filename);
                }
                $filename = $new_filename;
            } else {
                $message = "อัปโหลดไฟล์ไม่สำเร็จ";
                $msgClass = "danger";
            }
        }
    }

    if ($message === '') {
        $stmt = $conn->prepare("UPDATE menu SET name=?, price=?, image=?, category_id=? WHERE menu_id=?");
        $stmt->bind_param("sdsii", $name, $price, $filename, $category_id, $id);
        if ($stmt->execute()) {
            header("Location: adminmenu.php?msg=updated");
            exit;
        } else {
            $message = "เกิดข้อผิดพลาด: " . $stmt->error;
            $msgClass = "danger";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>แก้ไขเมนู • Deep Blue</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
/* ========= Deep Blue palette (match login/front_store) ========= */
:root{
  --text-strong:#ffffff;
  --text-normal:#e9eef6;
  --text-muted:#b9c6d6;

  --bg-grad1:#11161b;     /* background */
  --bg-grad2:#141b22;

  --surface:#1a2230;      /* cards */
  --surface-2:#192231;
  --surface-3:#202a3a;

  --ink:#e9eef6;
  --ink-muted:#b9c6d6;

  --brand-900:#f1f6ff;
  --brand-700:#d0d9e6;
  --brand-500:#3aa3ff;    /* accent */
  --brand-400:#7cbcfd;
  --brand-300:#a9cffd;

  --ok:#22c55e; 
  --danger:#e53935;

  --shadow-lg:none;
  --shadow:none;
}

html,body{height:100%}
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-normal);
  font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  display:flex; align-items:center; justify-content:center;
  padding:24px;
}

a, a:hover{ color:var(--brand-400); text-decoration:none }

.wrap{width:100%; max-width:820px}
.card{
  background:var(--surface);
  border:1px solid rgba(255,255,255,.08);
  border-radius:18px;
  box-shadow:none;
  overflow:hidden;
  color:var(--text-normal);
}
.card-head{
  padding:18px 22px;
  background: var(--surface-2);
  border-bottom:1px solid rgba(255,255,255,.10);
  display:flex; align-items:center; justify-content:space-between;
}
.title{ margin:0; font-weight:900; color:var(--text-strong); letter-spacing:.2px }
.crumb .btn{ font-weight:800 }

.card-body{ padding:22px }
label{ font-weight:700; color:var(--brand-300) }
.form-control, .custom-select{
  background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.12);
  color:var(--text-normal);
  border-radius:12px; padding:.65rem .9rem;
  transition:border-color .18s, box-shadow .18s, background .18s;
}
.form-control::placeholder{ color:#9da6ae }
.form-control:focus, .custom-select:focus{
  background:#202a3a;
  border-color:var(--brand-500);
  box-shadow:0 0 0 .18rem rgba(58,163,255,.25);
  color:var(--text-strong);
}
.custom-select{ appearance:none; -moz-appearance:none; -webkit-appearance:none; }
.help{ font-size:.85rem; color:#a6b6c4 }

.img-preview{
  display:block; width:200px; height:200px; object-fit:cover;
  border-radius:14px; border:1px solid rgba(255,255,255,.10); background:#0f141a;
  box-shadow:none;
}

.pill{
  display:inline-flex; align-items:center; gap:6px; padding:6px 10px;
  border-radius:999px; font-weight:800; border:1px solid rgba(255,255,255,.14);
  background:var(--surface-2); color:var(--text-normal);
}

/* Upload zone */
.dropzone{
  border:2px dashed rgba(58,163,255,.55); border-radius:14px; padding:16px;
  background:var(--surface-2); transition:all .15s;
  text-align:center; color:var(--brand-300);
}
.dropzone.drag{ background:var(--surface-3); border-color:var(--brand-400); }
#fileMeta{ color:var(--brand-900) }

/* Buttons */
.btn-main{
  background: var(--brand-500);
  color:#fff; border:1px solid #1e6acc; font-weight:900;
  padding:.85rem 1.15rem; border-radius:14px; min-width:220px;
}
.btn-main:hover{ filter:brightness(1.05) }
.btn-main:active{ transform: translateY(1px) }

.btn-ghost{
  background:var(--surface-2);
  border:1px solid rgba(255,255,255,.18);
  color:var(--brand-300); font-weight:800;
  border-radius:12px; padding:.6rem .9rem;
}
.btn-ghost:hover{ background:var(--surface-3); }

/* Alerts */
.alert{ border-radius:12px; font-weight:700 }
.alert-danger{
  background:rgba(229,57,53,.12);
  border-color:rgba(229,57,53,.35);
  color:#ffb3b1;
}

/* Toast */
.toast-mini{
  position:fixed; right:16px; bottom:16px; z-index:9999;
  background:var(--surface-3); color:var(--text-strong); padding:10px 14px; border-radius:12px;
  border:1px solid rgba(255,255,255,.12);
  box-shadow:none;
  font-weight:800; display:none;
}
.toast-mini.show{ display:block; animation:fade .18s ease-out }
@keyframes fade{ from{opacity:.2; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }

/* a11y */
:focus-visible{ outline:3px solid rgba(58,163,255,.45); outline-offset:3px; border-radius:10px }

/* tweak bootstrap wheel */
.custom-select, .form-control { color-scheme: dark; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="card-head">
      <h3 class="title"><i class="bi bi-egg-fried"></i> แก้ไขเมนู</h3>
      <div class="crumb">
        <a href="adminmenu.php" class="btn btn-ghost btn-sm"><i class="bi bi-arrow-left"></i> กลับรายการเมนู</a>
      </div>
    </div>

    <?php if (!empty($message)): ?>
      <div class="m-3 mt-3">
        <div class="alert alert-<?php echo htmlspecialchars($msgClass, ENT_QUOTES, 'UTF-8'); ?> mb-0">
          <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="card-body">
      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-row">
          <div class="col-md-8">
            <div class="form-group">
              <label for="name">ชื่อเมนู</label>
              <input type="text" id="name" name="name" class="form-control" required
                     value="<?= htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8') ?>">
              <small class="help">ตัวอย่าง: ชาไทยเย็น, อเมริกาโน่, ขนมปังปิ้ง</small>
            </div>

            <div class="form-group">
              <label for="price">ราคา (บาท)</label>
              <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required
                     value="<?= htmlspecialchars((string)$menu['price'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="form-group">
              <label for="category_id">หมวดหมู่</label>
              <select id="category_id" name="category_id" class="custom-select" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php
                  if ($categories && $categories->num_rows>=0) $categories->data_seek(0);
                  while ($cat = $categories->fetch_assoc()):
                ?>
                  <option value="<?= (int)$cat['category_id']; ?>"
                    <?= ((int)$cat['category_id'] === (int)$menu['category_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="col-md-4">
            <label>รูปภาพเดิม</label>
            <div class="mb-2">
              <?php
                $imgPathFs   = __DIR__ . '/images/' . ($menu['image'] ?? '');
                $imgPathHtml = 'images/' . ($menu['image'] ?? '');
                if (!empty($menu['image']) && file_exists($imgPathFs)):
              ?>
                <img src="<?= htmlspecialchars($imgPathHtml, ENT_QUOTES, 'UTF-8') ?>" alt="รูปภาพเมนู" class="img-preview" id="imgOld">
              <?php else: ?>
                <div class="pill"><i class="bi bi-image"></i> ไม่มีรูปภาพ</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- อัปโหลดรูปใหม่ -->
        <div class="form-group">
          <label for="image">เปลี่ยนรูปภาพ (ถ้ามี)</label>
          <div id="dz" class="dropzone" tabindex="0" aria-label="ลากไฟล์รูปมาวางเพื่ออัปโหลด">
            <div class="mb-2"><i class="bi bi-cloud-arrow-up"></i> ลากรูปมาวางที่นี่ หรือกดปุ่มเลือกไฟล์</div>
            <input type="file" id="image" name="image" class="d-none" accept="image/*">
            <div class="d-flex justify-content-center" style="gap:8px; flex-wrap:wrap">
              <button type="button" class="btn btn-ghost btn-sm" id="btnPick"><i class="bi bi-file-earmark-image"></i> เลือกไฟล์</button>
              <button type="button" class="btn btn-ghost btn-sm" id="btnClear"><i class="bi bi-x-circle"></i> ล้างไฟล์</button>
            </div>
            <div id="fileMeta" class="mt-2" style="font-weight:700"></div>
            <div class="mt-2"><img id="preview" class="img-preview" style="display:none" alt=""></div>
            <small class="help d-block mt-2">รองรับ JPG/PNG/GIF/WebP ขนาดไม่เกิน 5MB</small>
          </div>
        </div>

        <div class="mt-3">
          <button type="submit" class="btn-main"><i class="bi bi-save"></i> บันทึกการแก้ไข</button>
          <a href="adminmenu.php" class="btn btn-ghost ml-2"><i class="bi bi-arrow-left-circle"></i> ย้อนกลับ</a>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="toast" class="toast-mini"></div>

<script>
  // ===== Helpers =====
  const toast = (t)=>{ const el=document.getElementById('toast'); el.textContent=t; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'),1500); };
  const $ = (sel,root=document)=>root.querySelector(sel);
  const dz = $('#dz'), input = $('#image'), pick = $('#btnPick'), clearBtn = $('#btnClear'), meta = $('#fileMeta'), preview = $('#preview');

  const LIMIT = 5*1024*1024; // 5MB
  const ALLOW = ['image/jpeg','image/png','image/gif','image/webp'];

  function resetPreview(){
    preview.src = ''; preview.style.display='none';
    meta.textContent = '';
    input.value = '';
  }
  function fileInfo(f){
    const kb = (f.size/1024).toFixed(0);
    meta.innerHTML = `<i class="bi bi-file-earmark"></i> ${f.name} • ${kb} KB`;
  }
  function showPreview(f){
    const url = URL.createObjectURL(f);
    preview.src = url; preview.style.display='block';
    preview.onload = () => URL.revokeObjectURL(url);
  }
  function validate(f){
    if(!f) return true;
    if(!ALLOW.includes(f.type)){ toast('ไฟล์ต้องเป็น JPG/PNG/GIF/WebP'); return false; }
    if(f.size>LIMIT){ toast('ไฟล์เกิน 5MB'); return false; }
    return true;
  }

  pick.addEventListener('click', ()=> input.click());
  clearBtn.addEventListener('click', ()=> resetPreview());
  input.addEventListener('change', ()=>{
    const f = input.files && input.files[0];
    if(!f){ resetPreview(); return; }
    if(!validate(f)){ resetPreview(); return; }
    fileInfo(f); showPreview(f);
  });

  // Drag & drop
  ;['dragenter','dragover'].forEach(ev=> dz.addEventListener(ev, e=>{ e.preventDefault(); dz.classList.add('drag'); }));
  ;['dragleave','drop'].forEach(ev=> dz.addEventListener(ev, e=>{ e.preventDefault(); dz.classList.remove('drag'); }));
  dz.addEventListener('drop', (e)=>{
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if(!f) return;
    if(!validate(f)){ resetPreview(); return; }
    const dt = new DataTransfer(); dt.items.add(f); input.files = dt.files;
    fileInfo(f); showPreview(f);
  });

  // a11y: Enter/Space เปิด file picker
  dz.addEventListener('keydown', (e)=>{ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); input.click(); }});
</script>
</body>
</html>

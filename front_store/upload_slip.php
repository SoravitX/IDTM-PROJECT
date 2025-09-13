<?php
// front_store/upload_slip.php — อัปโหลดสลิปโอนเงินหลังสั่งออเดอร์
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// อ่านออเดอร์
$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) { header("Location: front_store.php"); exit; }

// ดึงข้อมูลออเดอร์ (เพื่อโชว์ยอด/ตรวจสิทธิ์เจ้าของ)
$stmt = $conn->prepare("SELECT o.order_id, o.user_id, o.total_price, o.status, o.order_time, u.username
                        FROM orders o
                        LEFT JOIN users u ON u.user_id=o.user_id
                        WHERE o.order_id=?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { header("Location: front_store.php"); exit; }
// ถ้าต้องจำกัดว่า คนอัปต้องเป็นเจ้าของออเดอร์
// if ((int)$order['user_id'] !== (int)$_SESSION['uid']) { header("Location: front_store.php"); exit; }

// ตารางเก็บสลิป (สร้างอัตโนมัติถ้ายังไม่มี)
$conn->query("
CREATE TABLE IF NOT EXISTS payment_slips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  user_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime VARCHAR(64) NOT NULL,
  size_bytes INT NOT NULL,
  uploaded_at DATETIME NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  INDEX(order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ช่วยฟอร์แมตเงิน
function money_fmt($n){ return number_format((float)$n, 2); }

// โฟลเดอร์เก็บสลิป
$upload_dir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$slip_dir   = $upload_dir . '/slips';
if (!is_dir($slip_dir)) { @mkdir($slip_dir, 0775, true); }

$success_msg = '';
$error_msg   = '';

// ====== อัปโหลดไฟล์ ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['slip'])) {

  if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'อัปโหลดไฟล์ไม่สำเร็จ';
  } else {
    $tmp  = $_FILES['slip']['tmp_name'];
    $name = $_FILES['slip']['name'];
    $size = (int)$_FILES['slip']['size'];

    // จำกัดขนาดไฟล์ <= 5MB
    if ($size > 5 * 1024 * 1024) {
      $error_msg = 'ไฟล์ใหญ่เกินไป (เกิน 5MB)';
    } else {
      // ตรวจ MIME จริง
      $fi = new finfo(FILEINFO_MIME_TYPE);
      $mime = $fi->file($tmp);
      $allow = ['image/jpeg','image/png','image/webp','image/heic','image/heif'];
      if (!in_array($mime, $allow, true)) {
        $error_msg = 'อนุญาตเฉพาะรูปภาพ JPG/PNG/WebP/HEIC เท่านั้น';
      } else {
        // แปลง/บีบอัดเป็น JPG เพื่อให้ไฟล์เล็ก
        $target_name = 'slip_'.$order_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.jpg';
        $target_path = $slip_dir . '/' . $target_name;

        // ใช้ GD แปลงเป็น JPG คุณภาพ 82
        $ok = false;
        try {
          // โหลดภาพ
          switch ($mime) {
            case 'image/jpeg':
              $im = imagecreatefromjpeg($tmp); break;
            case 'image/png':
              $im = imagecreatefrompng($tmp); imagepalettetotruecolor($im); imagealphablending($im, true); imagesavealpha($im, false); break;
            case 'image/webp':
              if (!function_exists('imagecreatefromwebp')) throw new Exception('no webp support');
              $im = imagecreatefromwebp($tmp); break;
            case 'image/heic':
            case 'image/heif':
              // ถ้า PHP ไม่มีตัวอ่าน HEIC/HEIF จะเซฟเป็นไฟล์เดิมแทน
              $im = null;
              break;
            default:
              $im = null;
          }

          if ($im) {
            // ลดขนาดด้านยาวสุด ~1500px เพื่อความเบา (ถ้ารูปใหญ่)
            $w = imagesx($im); $h = imagesy($im);
            $maxSide = 1500;
            if (max($w,$h) > $maxSide) {
              $ratio = min($maxSide/$w, $maxSide/$h);
              $nw = (int)round($w*$ratio); $nh=(int)round($h*$ratio);
              $dst = imagecreatetruecolor($nw, $nh);
              imagecopyresampled($dst, $im, 0,0,0,0, $nw,$nh, $w,$h);
              imagedestroy($im);
              $im = $dst;
            }
            imagejpeg($im, $target_path, 82);
            imagedestroy($im);
            $ok = file_exists($target_path);
          } else {
            // กรณีอ่านไม่ได้ → เก็บไฟล์เดิมเป็น .jpg (rename)
            $ok = move_uploaded_file($tmp, $target_path);
          }
        } catch (Throwable $e) {
          // fallback
          $ok = move_uploaded_file($tmp, $target_path);
        }

        if ($ok) {
          // บันทึกฐานข้อมูล
          $rel_path = 'uploads/slips/'.$target_name; // path แบบ relative จากโฟลเดอร์ front_store/..
          $stmt = $conn->prepare("INSERT INTO payment_slips (order_id, user_id, file_path, mime, size_bytes, uploaded_at, note)
                                  VALUES (?,?,?,?,?, NOW(), ?)");
          $note = trim((string)($_POST['note'] ?? ''));
          $mime_save = 'image/jpeg';  // เราบันทึกเป็น jpg แล้ว
          $fsz = filesize($target_path) ?: $size;
          $uid = (int)$_SESSION['uid'];
          $stmt->bind_param("iissis", $order_id, $uid, $rel_path, $mime_save, $fsz, $note);
          $stmt->execute(); $stmt->close();

          // อัพเดตสถานะออเดอร์เป็น 'pending' เหมือนเดิม (หรือจะตั้ง 'awaiting_payment' ก็ได้)
          // $conn->query("UPDATE orders SET status='pending' WHERE order_id={$order_id} LIMIT 1");

          $success_msg = 'อัปโหลดสลิปเรียบร้อยแล้ว ขอบคุณค่ะ/ครับ';
        } else {
          $error_msg = 'บันทึกรูปไม่สำเร็จ';
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>อัปโหลดสลิป • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep:#0D4071; --psu-sky:#29ABE2; --ring:#7dd3fc;
}
body{background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif;}
.wrap{max-width:920px; margin:28px auto; padding:0 16px}
.cardx{ background:#fff; color:#0b2746; border-radius:16px; border:1px solid #cfe2ff; box-shadow:0 10px 24px rgba(0,0,0,.2)}
.badge-o{background:#0D4071; color:#fff; border-radius:999px; padding:.35rem .7rem; font-weight:800}
.hint{color:#335}
.dz{ border:2px dashed #8bb6ff; border-radius:14px; padding:18px; text-align:center; background:#f6faff; cursor:pointer}
.dz.drag{ background:#e9f3ff; border-color:#2b74ff}
.preview{ display:flex; gap:12px; flex-wrap:wrap; margin-top:10px }
.preview img{ max-width:220px; border-radius:10px; border:1px solid #dce8ff }
:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="m-0">อัปโหลดสลิปการชำระเงิน</h4>
    <a class="btn btn-outline-light" href="front_store.php">กลับไปหน้าเมนู</a>
  </div>

  <div class="cardx p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="h5 mb-1">ออเดอร์ <span class="badge-o">#<?= (int)$order['order_id'] ?></span></div>
        <div class="hint">ยอดที่ต้องชำระ: <strong><?= money_fmt($order['total_price']) ?> ฿</strong></div>
      </div>
      <div class="text-right">
        <div class="hint mb-1">สถานะปัจจุบัน: <strong><?= htmlspecialchars($order['status'],ENT_QUOTES,'UTF-8') ?></strong></div>
        <div class="hint">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '',ENT_QUOTES,'UTF-8') ?></div>
      </div>
    </div>

    <?php if ($success_msg): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success_msg,ENT_QUOTES,'UTF-8') ?></div>
      <a href="checkout.php" class="btn btn-primary">ไปหน้า Order</a>
      <a href="front_store.php" class="btn btn-outline-secondary">สั่งต่อ</a>
    <?php else: ?>
      <?php if($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg,ENT_QUOTES,'UTF-8') ?></div><?php endif; ?>

      <form method="post" enctype="multipart/form-data" id="frmSlip">
        <div class="dz mb-2" id="dropzone">
          <div class="lead mb-1">ลากไฟล์มาวางที่นี่ หรือกดเลือกไฟล์</div>
          <div class="small text-muted">รองรับ JPG, PNG, WebP, HEIC ขนาดไม่เกิน 5MB</div>
          <input type="file" name="slip" id="file" accept="image/*" capture="environment" class="d-none">
          <button type="button" class="btn btn-info mt-2" id="btnChoose">เลือกไฟล์ / ถ่ายภาพ</button>
        </div>

        <div class="form-group">
          <label>หมายเหตุ (ถ้ามี)</label>
          <input type="text" name="note" class="form-control" placeholder="เช่น โอนจากธนาคาร... เวลา ...">
        </div>

        <div class="preview" id="preview"></div>

        <div class="mt-3">
          <button class="btn btn-success" type="submit">อัปโหลดสลิป</button>
          <a class="btn btn-outline-secondary" href="front_store.php">ยกเลิก</a>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
const drop = document.getElementById('dropzone');
const inp  = document.getElementById('file');
const btn  = document.getElementById('btnChoose');
const prev = document.getElementById('preview');

btn.addEventListener('click', ()=> inp.click());
drop.addEventListener('click', ()=> inp.click());

;['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e=>{
  e.preventDefault(); e.stopPropagation(); drop.classList.add('drag');
}));
;['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e=>{
  e.preventDefault(); e.stopPropagation(); drop.classList.remove('drag');
}));
drop.addEventListener('drop', e=>{
  if (e.dataTransfer.files && e.dataTransfer.files[0]) {
    inp.files = e.dataTransfer.files;
    renderPreview();
  }
});
inp.addEventListener('change', renderPreview);

function renderPreview(){
  prev.innerHTML = '';
  const f = inp.files && inp.files[0];
  if (!f) return;
  const ok = /image\/(jpeg|png|webp|heic|heif)/i.test(f.type);
  if (!ok) { alert('ไฟล์ต้องเป็นรูปภาพเท่านั้น'); inp.value=''; return; }
  const url = URL.createObjectURL(f);
  const img = new Image(); img.src = url; img.onload = ()=>{
    prev.innerHTML = '';
    prev.appendChild(img);
    URL.revokeObjectURL(url);
  };
}
</script>
</body>
</html>

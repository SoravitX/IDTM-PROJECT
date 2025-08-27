<?php
// admin/edit_menu.php
declare(strict_types=1);
include '../db.php';

// รับ id เมนูที่ต้องการแก้ไข
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: adminmenu.php');
    exit;
}

$message = '';
$msgClass = '';

// ดึงข้อมูลเมนูจากฐานข้อมูล (ตารางใหม่ชื่อ 'menu')
$stmt = $conn->prepare("SELECT menu_id, name, price, image, category_id FROM menu WHERE menu_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$menu = $result->fetch_assoc();
$stmt->close();

if (!$menu) {
    header('Location: adminmenu.php');
    exit;
}

// ดึงหมวดหมู่ทั้งหมด
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);

    $filename = $menu['image']; // เก็บชื่อไฟล์เดิมไว้ก่อน

    // โฟลเดอร์เก็บภาพ (filesystem) และ path สำหรับแสดงผล
    $target_dir_fs   = __DIR__ . "/images/";
    if (!is_dir($target_dir_fs)) {
        mkdir($target_dir_fs, 0755, true);
    }

    // ตรวจสอบว่ามีการอัปโหลดไฟล์ภาพใหม่หรือไม่
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
            // ตั้งชื่อไฟล์ใหม่กันซ้ำ
            $new_filename   = sprintf("%s_%s.%s", time(), bin2hex(random_bytes(5)), $ext);
            $target_file_fs = $target_dir_fs . $new_filename;

            if (move_uploaded_file($f['tmp_name'], $target_file_fs)) {
                // ลบไฟล์เดิมถ้ามี
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
            // ✅ สำเร็จ → กลับหน้าเมนูอาหาร
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
<title>แก้ไขเมนู</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
<style>
    body {
        background-color: #FED8B1;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        min-height: 100vh;
        display: flex; justify-content: center; align-items: center; padding: 20px;
    }
    .card {
        background-color: #fff8f0; border-radius: 15px; box-shadow: 0 8px 20px rgba(111, 78, 55, 0.3);
        width: 100%; max-width: 480px; padding: 30px 40px;
    }
    h3 { color: #6F4E37; text-align: center; margin-bottom: 25px; font-weight: 700; letter-spacing: 1px; }
    label { font-weight: 600; color: #6F4E37; }
    input[type="text"], input[type="number"], select {
        background-color: #FED8B1; border: 2px solid #ECB176; border-radius: 8px; padding: 10px 15px;
        font-size: 1rem; color: #6F4E37; transition: border-color 0.3s ease;
    }
    input[type="text"]:focus, input[type="number"]:focus, select:focus {
        outline: none; border-color: #A67B5B; background-color: #FFF3E6;
    }
    .form-control-file { color: #6F4E37; }
    button.btn-primary {
        background-color: #6F4E37; border: none; padding: 12px; font-weight: 700; font-size: 1.1rem;
        border-radius: 25px; width: 100%; transition: background-color 0.3s ease;
    }
    button.btn-primary:hover { background-color: #A67B5B; }
    .alert { font-weight: 600; text-align: center; border-radius: 12px; }
    .alert-success { background-color: #ECB176; color: #4a2f14; }
    .alert-danger { background-color: #f8d7da; color: #721c24; }
    .img-preview { display: block; margin: 15px auto; max-width: 150px; border-radius: 10px; box-shadow: 0 2px 6px rgba(111, 78, 55, 0.3); }
</style>
</head>
<body>
<div class="card">
    <h3>แก้ไขเมนู</h3>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo htmlspecialchars($msgClass, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">ชื่อเมนู</label>
            <input type="text" id="name" name="name" class="form-control" required autofocus
                value="<?= htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="price">ราคา (บาท)</label>
            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required
                value="<?= htmlspecialchars((string)$menu['price'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="form-group">
            <label for="category_id">หมวดหมู่</label>
            <select id="category_id" name="category_id" class="form-control" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= (int)$cat['category_id']; ?>" 
                        <?= ((int)$cat['category_id'] === (int)$menu['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label>รูปภาพเดิม</label><br>
            <?php
            $imgPathFs   = __DIR__ . '/images/' . ($menu['image'] ?? '');
            $imgPathHtml = 'images/' . ($menu['image'] ?? '');
            if (!empty($menu['image']) && file_exists($imgPathFs)): ?>
                <img src="<?= htmlspecialchars($imgPathHtml, ENT_QUOTES, 'UTF-8') ?>" alt="รูปภาพเมนู" class="img-preview">
            <?php else: ?>
                <p>ไม่มีรูปภาพ</p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="image">เปลี่ยนรูปภาพ (ถ้ามี)</label>
            <input type="file" id="image" name="image" class="form-control-file" accept="image/*">
        </div>

        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        <a href="adminmenu.php" class="btn btn-secondary btn-block mt-3">ย้อนกลับ</a>
    </form>
</div>
</body>
</html>

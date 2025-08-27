<?php
// admin/add_menu.php
declare(strict_types=1);

require __DIR__ . '/../db.php'; // เชื่อมต่อฐานข้อมูล

$message = '';
$msgClass = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);

    // โฟลเดอร์ภาพภายใต้ admin/
    $target_dir = __DIR__ . "/images/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    // ตรวจสอบไฟล์อัปโหลดเบื้องต้น
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $message = "อัปโหลดไฟล์ไม่สำเร็จ";
    } else {
        // ตรวจชนิดไฟล์และขนาด
        $f = $_FILES['image'];
        $allowedExt  = ['jpg','jpeg','png','gif','webp'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            $message = "รองรับเฉพาะไฟล์รูป: " . implode(', ', $allowedExt);
        } elseif ($f['size'] > 5 * 1024 * 1024) { // 5MB
            $message = "ไฟล์ใหญ่เกินไป (สูงสุด 5MB)";
        } else {
            // ยืนยันว่าเป็นรูปจริง
            $imgInfo = @getimagesize($f['tmp_name']);
            if ($imgInfo === false) {
                $message = "ไฟล์ที่อัปโหลดไม่ใช่ภาพ";
            } else {
                // ตั้งชื่อไฟล์ใหม่ให้ไม่ชน
                $filename = sprintf("%s_%s.%s", time(), bin2hex(random_bytes(5)), $ext);
                $target_file = $target_dir . $filename;

                // ย้ายไฟล์
                if (!move_uploaded_file($f['tmp_name'], $target_file)) {
                    $message = "ไม่สามารถบันทึกรูปภาพได้";
                } else {
                    // บันทึก DB — สคีมาใหม่ใช้ตาราง `menu`
                    $relPathForHtml = $filename; // เก็บเฉพาะชื่อไฟล์ ในหน้าแสดงจะใช้ admin/images/<ชื่อไฟล์>

                    $stmt = $conn->prepare("
                        INSERT INTO menu (name, price, image, category_id)
                        VALUES (?, ?, ?, ?)
                    ");
                    if (!$stmt) {
                        $message = "เตรียมคำสั่งล้มเหลว: " . $conn->error;
                    } else {
                        $stmt->bind_param("sdsi", $name, $price, $relPathForHtml, $category_id);
                        if ($stmt->execute()) {
                            // สำเร็จ → กลับหน้าแสดงพร้อมแจ้งเตือน
                            header("Location: adminmenu.php?msg=added");
                            exit;
                        } else {
                            $message = "บันทึกข้อมูลล้มเหลว: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// ดึงหมวดหมู่มาแสดงใน select
$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>เพิ่มเมนูใหม่</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" />
<link rel="stylesheet" href="style(add_menu).css" />
<style>
    body {
        background-color: #FED8B1;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        padding: 20px;
        display: flex; justify-content: center; align-items: flex-start;
        min-height: 100vh;
    }
    .card {
        background-color: #fff8f0;
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(111,78,55,.3);
        padding: 30px 40px; max-width: 500px; width: 100%; color: #6F4E37;
    }
    h3 { font-weight: 700; margin-bottom: 25px; }
    .btn-primary { background-color: #A67B5B; border: none; padding: 12px 30px;
        font-weight: 700; border-radius: 25px; transition: .3s; }
    .btn-primary:hover { background-color: #6F4E37; }
    .btn-secondary { margin-top: 15px; border-radius: 25px; }
    label { font-weight: 600; }
    .alert-danger { background-color: #ec5757; color:#fff; border-radius: 8px; padding:10px; margin-bottom:15px; }
</style>
</head>
<body>
<div class="card">
    <h3>เพิ่มเมนูใหม่</h3>

    <?php if (!empty($message)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-group">
            <label for="name">ชื่อเมนู</label>
            <input type="text" id="name" name="name" class="form-control" required autofocus>
        </div>

        <div class="form-group">
            <label for="price">ราคา (บาท)</label>
            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label for="category_id">หมวดหมู่</label>
            <select id="category_id" name="category_id" class="form-control" required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= (int)$cat['category_id']; ?>">
                        <?= htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="image">รูปภาพ</label>
            <input type="file" id="image" name="image" class="form-control-file" accept="image/*" required>
        </div>

        <button type="submit" class="btn btn-primary">บันทึกเมนู</button>
        <a href="adminmenu.php" class="btn btn-secondary btn-block mt-3">ย้อนกลับ</a>
    </form>
</div>
</body>
</html>

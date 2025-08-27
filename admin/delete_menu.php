<?php
// admin/delete_menu.php
declare(strict_types=1);
include '../db.php'; // เชื่อมต่อฐานข้อมูล

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: adminmenu.php?msg=invalidid");
    exit;
}

// ถ้ามีการยืนยันลบ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    // ดึงชื่อไฟล์ภาพจากตารางใหม่ 'menu'
    $stmt = $conn->prepare("SELECT image FROM menu WHERE menu_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $menu = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($menu) {
        // ลบไฟล์ภาพ (ถ้ามีและไม่ใช่ค่าว่าง)
        $imageFile = trim((string)$menu['image']);
        $imagePathFs = __DIR__ . "/images/" . $imageFile; // filesystem path
        if ($imageFile !== '' && file_exists($imagePathFs)) {
            @unlink($imagePathFs);
        }

        // ลบข้อมูลเมนู
        $stmt = $conn->prepare("DELETE FROM menu WHERE menu_id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: adminmenu.php?msg=deleted");
            exit;
        } else {
            die("ลบข้อมูลล้มเหลว: " . $conn->error);
        }
    } else {
        header("Location: adminmenu.php?msg=notfound");
        exit;
    }
} else {
    // แสดงข้อมูลเมนูก่อนลบ (JOIN categories เพื่อเอาชื่อหมวดหมู่) จากตารางใหม่ 'menu'
    $stmt = $conn->prepare("
        SELECT m.menu_id, m.name, m.price, m.image, c.category_name
        FROM menu m
        LEFT JOIN categories c ON m.category_id = c.category_id
        WHERE m.menu_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $menu = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$menu) {
        header("Location: adminmenu.php?msg=notfound");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>ยืนยันการลบเมนู</title>
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
        width: 100%; max-width: 600px; padding: 30px 40px; text-align: center;
    }
    h3 { color: #6F4E37; margin-bottom: 25px; font-weight: 700; }
    .menu-image { max-width: 250px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(111, 78, 55, 0.3); }
    .btn-danger {
        background-color: #A67B5B; border: none; padding: 12px 30px; font-weight: 700;
        border-radius: 25px; transition: background-color .3s ease; margin-right: 15px;
    }
    .btn-danger:hover { background-color: #6F4E37; }
    .btn-secondary { padding: 12px 30px; border-radius: 25px; }
    .details { text-align: left; color: #6F4E37; font-weight: 600; margin-bottom: 30px; font-size: 1.1rem; }
</style>
</head>
<body>
<div class="card">
    <h3>ยืนยันการลบเมนู</h3>

    <?php
      $img = htmlspecialchars($menu['image'] ?? '', ENT_QUOTES, 'UTF-8');
      $imgSrc = $img !== '' ? "images/$img" : "images/default.png";
    ?>
    <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8'); ?>" class="menu-image">

    <div class="details">
        <p><strong>ชื่อเมนู:</strong> <?= htmlspecialchars($menu['name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>ราคา:</strong> <?= number_format((float)$menu['price'], 2); ?> บาท</p>
        <p><strong>หมวดหมู่:</strong> <?= htmlspecialchars($menu['category_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <form method="POST">
        <input type="hidden" name="confirm_delete" value="1" />
        <button type="submit" class="btn btn-danger">ยืนยันลบ</button>
        <a href="adminmenu.php" class="btn btn-secondary">ยกเลิก</a>
    </form>
</div>
</body>
</html>

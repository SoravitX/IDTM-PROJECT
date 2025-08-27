<?php
// adminmenu.php — PSU theme + toggle sale (is_active)
declare(strict_types=1);

include_once __DIR__ . '/../db.php';


mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// รับหมวดหมู่
$category_sql = "SELECT category_id, category_name FROM categories ORDER BY category_id";
$category_result = $conn->query($category_sql);

// filter
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($category_id > 0) {
    $stmt = $conn->prepare("
        SELECT m.menu_id, m.name, m.price, m.image, m.is_active, c.category_name
        FROM menu m
        LEFT JOIN categories c ON m.category_id = c.category_id
        WHERE m.category_id = ?
        ORDER BY m.menu_id
    ");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $sql = "
        SELECT m.menu_id, m.name, m.price, m.image, m.is_active, c.category_name
        FROM menu m
        LEFT JOIN categories c ON m.category_id = c.category_id
        ORDER BY m.menu_id
    ";
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <title>จัดการเมนู - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --psu-deep-blue:#0D4071;  /* PSU Deep Blue */
      --psu-ocean-blue:#4173BD; /* PSU Ocean Blue */
      --psu-andaman:#0094B3;    /* PSU Andaman Blue */
      --psu-sky:#29ABE2;        /* PSU Sky Blue */
      --psu-river:#4EC5E0;      /* PSU River Blue */
      --psu-sritrang:#BBB4D8;   /* PSU Sritrang */
      --ink:#0b2746;
      --card:#ffffff;
      --softBorder:#e7e9f2;
      --shadow:0 12px 28px rgba(0,0,0,.14);
    }
    body{
      margin:0; font-family:"Segoe UI", Tahoma, sans-serif; color:#fff;
      background:linear-gradient(135deg,var(--psu-deep-blue),var(--psu-ocean-blue));
    }
    .container{max-width:1280px;margin:20px auto;padding:0 16px}
    .bar{
      display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;
    }
    .title{font-weight:900; letter-spacing:.3px}
    .category-bar{
      background:rgba(255,255,255,.08); border:1px solid var(--psu-sritrang);
      padding:10px 12px; border-radius:12px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;
      box-shadow:var(--shadow);
    }
    .category-bar a{
      text-decoration:none; color:#fff; border:1px solid var(--psu-ocean-blue);
      padding:6px 12px; border-radius:999px; font-weight:700; background:rgba(255,255,255,.06);
    }
    .category-bar a.active{
      background:linear-gradient(180deg,var(--psu-sky),var(--psu-river)); color:#063152; border-color:#063152;
    }
    .add-btn{
      display:inline-block; margin:14px 0; text-decoration:none; font-weight:800; color:#063152;
      background:linear-gradient(180deg,var(--psu-river),var(--psu-sky)); border:1px solid #063152;
      padding:8px 14px; border-radius:10px;
    }

    .menu-grid{
      display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:14px;
    }
    .menu-card{
      background:var(--card); color:var(--ink); border:1px solid var(--softBorder);
      border-radius:14px; overflow:hidden; box-shadow:var(--shadow); display:flex; flex-direction:column;
    }
    .menu-card img{ width:100%; height:150px; object-fit:cover; background:#eef5ff }
    .menu-card .meta{ padding:10px 12px }
    .menu-card h3{ margin:0 0 6px; font-size:1.05rem; color:#0D4071 }
    .menu-card small{ color:#6b7280 }
    .badge{
      display:inline-block; font-weight:800; font-size:.78rem; border-radius:999px; padding:4px 8px;
      border:1px solid #dfe6f5; background:#f5f9ff; color:#0D4071;
    }
    .badge.off{ background:#ffe8e8; color:#9b1c1c; border-color:#ffc8c8 }
    .price{ font-weight:900; color:#29ABE2; }

    .card-actions{
      margin-top:auto; display:flex; gap:8px; padding:10px 12px; background:#f5f8fd; border-top:1px solid #e9eefb;
    }
    .btn{
      display:inline-block; text-decoration:none; text-align:center; font-weight:800; padding:8px 10px; border-radius:10px; border:1px solid transparent;
    }
    .btn-edit{ background:#0D4071; color:#fff; border-color:#0D4071 }
    .btn-toggle-on{ background:#e53935; color:#fff; }   /* ปิดการขาย */
    .btn-toggle-off{ background:#2e7d32; color:#fff; }  /* เปิดการขาย */

    .flash{
      background:#2e7d32; color:#fff; border-radius:8px; padding:10px 12px; margin:12px 0;
    }
  </style>
</head>
<body>
<div class="container">
  <div class="bar">
    <h2 class="title">จัดการเมนู</h2>
  </div>

  <?php if (isset($_GET['msg']) && $_GET['msg']==='added'): ?>
    <div class="flash">เพิ่มเมนูเรียบร้อยแล้ว</div>
  <?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg']==='updated'): ?>
    <div class="flash">แก้ไขเมนูเรียบร้อยแล้ว</div>
  <?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg']==='toggled_on'): ?>
    <div class="flash">เปิดการขายเมนูเรียบร้อย</div>
  <?php endif; ?>
  <?php if (isset($_GET['msg']) && $_GET['msg']==='toggled_off'): ?>
    <div class="flash" style="background:#e53935">ปิดการขายเมนูเรียบร้อย</div>
  <?php endif; ?>

  <div class="category-bar">
    <strong>หมวดหมู่:</strong>
    <a href="adminmenu.php" class="<?php echo $category_id === 0 ? 'active' : ''; ?>">ทั้งหมด</a>
    <?php while ($cat = $category_result->fetch_assoc()): ?>
      <a href="?category_id=<?php echo (int)$cat['category_id']; ?>"
         class="<?php echo ($category_id === (int)$cat['category_id']) ? 'active' : ''; ?>">
        <?php echo htmlspecialchars($cat['category_name']); ?>
      </a>
    <?php endwhile; ?>
  </div>

  <a class="add-btn" href="add_menu.php">+ เพิ่มเมนู</a>

  <div class="menu-grid">
    <?php if ($result && $result->num_rows > 0): ?>
      <?php while ($row = $result->fetch_assoc()):
        $imageName = trim((string)$row['image']);
        $imgUrl  = "images/" . htmlspecialchars($imageName ?: 'default.png');
        $imgFile = __DIR__ . "/images/" . ($imageName ?: 'default.png');
        if (empty($imageName) || !file_exists($imgFile)) $imgUrl = "images/default.png";
        $isActive = (int)$row['is_active'] === 1;
      ?>
        <div class="menu-card">
          <img src="<?php echo $imgUrl; ?>" alt="<?php echo htmlspecialchars($row['name']); ?>">
          <div class="meta">
            <h3><?php echo htmlspecialchars($row['name']); ?></h3>
            <div class="price"><?php echo number_format((float)$row['price'], 2); ?> บาท</div>
            <p><small>หมวดหมู่: <?php echo htmlspecialchars($row['category_name'] ?? '-'); ?></small></p>
            <span class="badge <?php echo $isActive ? '' : 'off'; ?>">
              <?php echo $isActive ? 'กำลังขาย' : 'ปิดการขาย'; ?>
            </span>
          </div>
          <div class="card-actions">
            <a class="btn btn-edit" href="edit_menu.php?id=<?php echo (int)$row['menu_id']; ?>">แก้ไข</a>

            <?php if ($isActive): ?>
              <form method="post" action="toggle_sale.php" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo (int)$row['menu_id']; ?>">
                <input type="hidden" name="to" value="0">
                <button type="submit" class="btn btn-toggle-on" onclick="return confirm('ปิดการขายเมนูนี้?');">ปิดการขาย</button>
              </form>
            <?php else: ?>
              <form method="post" action="toggle_sale.php" style="margin:0;">
                <input type="hidden" name="id" value="<?php echo (int)$row['menu_id']; ?>">
                <input type="hidden" name="to" value="1">
                <button type="submit" class="btn btn-toggle-off">เปิดการขาย</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p>ไม่มีข้อมูลเมนู</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>

<?php
// adminmenu.php — Admin UI with same layout as front_store (PSU tone)
declare(strict_types=1);
include_once __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ---- data ---- */
$category_sql    = "SELECT category_id, category_name FROM categories ORDER BY category_id";
$category_result = $conn->query($category_sql);

$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$q           = trim((string)($_GET['q'] ?? ''));

$params = [];
$types  = '';
$sql = "
  SELECT m.menu_id, m.name, m.price, m.image, m.is_active, c.category_name
  FROM menu m
  LEFT JOIN categories c ON m.category_id=c.category_id
  WHERE 1=1
";
if ($category_id > 0) { $sql .= " AND m.category_id=?"; $types .= 'i'; $params[] = $category_id; }
if ($q !== '')        { $sql .= " AND m.name LIKE ?";   $types .= 's'; $params[] = '%'.$q.'%'; }
$sql .= " ORDER BY m.menu_id";

if ($types !== '') {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();
  $stmt->close();
} else {
  $result = $conn->query($sql);
}
function baht($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>PSU Blue Cafe • Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
/* ---------- Card look & feel ---------- */
:root{
  --accent:#2fb3ff;         /* สีหลักใหม่ */
  --accent-deep:#0D4071;
  --success:#2e7d32;
  --danger:#e53935;
  --muted:#6b7280;
}

.menu-grid{
  display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr));
  gap:16px;
}

.menu-card{
  position:relative;
  border-radius:16px;
  background:
    radial-gradient(180px 60px at 80% 0%, rgba(47,179,255,.20), transparent 60%),
    linear-gradient(180deg,#ffffff,#f7faff 60%, #f2f6ff);
  border:1px solid #e5ecfb;
  box-shadow:
    0 14px 32px rgba(5,35,70,.18),
    inset 0 1px 0 rgba(255,255,255,.8);
  overflow:hidden;
  display:flex; flex-direction:column;
  transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.menu-card:hover{
  transform:translateY(-4px);
  box-shadow:0 22px 50px rgba(5,35,70,.22);
  border-color:#d7e7ff;
}
.menu-card img{ width:100%; height:160px; object-fit:cover; display:block; }

/* top badge (status ribbon) */
.card-ribbon{
  position:absolute; top:10px; left:10px;
  background:rgba(13,64,113,.9);
  color:#fff; font-weight:800; font-size:.75rem;
  padding:6px 10px; border-radius:999px;
  box-shadow:0 10px 18px rgba(0,0,0,.18);
}
.menu-card.is-off .card-ribbon{ background:rgba(229,57,53,.95) }

/* meta area */
.menu-card .meta{ padding:12px 14px 10px; }
.menu-card h3{
  margin:0 0 6px; font-size:1.02rem; color:var(--accent-deep);
  line-height:1.2; font-weight:900;
}

/* ราคาแบบแคปซูล */
.price-pill{
  display:inline-flex; align-items:center; gap:6px;
  background:linear-gradient(180deg,#e8f6ff,#dff1ff);
  color:#0a4c7a; border:1px solid #cfe9ff; border-radius:999px;
  padding:6px 10px; font-weight:900;
}

/* หมวดหมู่ + สถานะ */
.info-line{ color:var(--muted); margin:8px 0 0 }
.badge-chip{
  display:inline-flex; align-items:center;
  gap:6px; margin-top:10px;
  background:#f3f6ff; color:#0D4071; border:1px solid #dfe6fb;
  border-radius:999px; padding:5px 10px; font-weight:800; font-size:.82rem;
}
.badge-chip.off{ background:#ffecee; color:#9b1c1c; border-color:#ffc9cf }

/* footer buttons */
.card-actions{
  margin-top:auto;
  display:flex; gap:10px; padding:12px; background:#f7f9ff; border-top:1px solid #e8eefc;
}
.btn{
  flex:0 0 auto; cursor:pointer;
  border:1px solid transparent; border-radius:12px;
  padding:9px 12px; font-weight:800; font-size:.92rem;
  box-shadow:0 6px 14px rgba(0,0,0,.10);
  transition:transform .08s ease, filter .12s ease;
}
.btn:active{ transform:translateY(1px); }

.btn-edit{ background:var(--accent-deep); color:#fff; border-color:#082b49; }
.btn-edit:hover{ filter:brightness(1.05) }
.btn-toggle-off{ background:var(--success); color:#fff; }
.btn-toggle-on{ background:var(--danger);  color:#fff; }

/* เมื่อปิดการขาย */
.menu-card.is-off img{ filter:grayscale(.1) saturate(.7) brightness(.95); }

/* ==== Theme like front_store ==== */
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2; --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ink:#0b2746; --shadow:0 14px 32px rgba(0,0,0,.24); --ring:#7dd3fc;
}
html,body{height:100%}
body{margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
  background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff}

.pos-shell{padding:12px; max-width:1600px; margin:0 auto;}
.topbar{position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18)}
.brand{font-weight:900; letter-spacing:.3px}
.searchbox{background:#fff; border:2px solid var(--psu-ocean-blue); color:#000;
  border-radius:999px; padding:.4rem .9rem; min-width:260px}
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}
.topbar .btn-primary{background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800}
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }
.pos-card{background:rgba(255,255,255,.08); border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:var(--shadow)}
.chips a{display:inline-flex; align-items:center; gap:6px; padding:7px 14px; margin:0 8px 10px 0; border-radius:999px;
  border:1px solid var(--psu-ocean-blue); color:#fff; text-decoration:none; font-weight:700; background:rgba(255,255,255,.05)}
.chips a.active{background:linear-gradient(180deg,var(--psu-sky),var(--psu-river)); color:#062d4f; border-color:#073c62; box-shadow:0 8px 18px rgba(0,0,0,.15)}

.flash{background:#2e7d32; color:#fff; border-radius:8px; padding:10px 12px; margin:12px 0}
.flash.danger{background:#e53935}
@media(max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} }
</style>
</head>
<body>
<div class="container-fluid pos-shell">

  <!-- Topbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • Admin</h4>

      <form class="form-inline" method="get" action="adminmenu.php">
        <input name="q" class="form-control form-control-sm searchbox mr-2"
               value="<?= htmlspecialchars($q,ENT_QUOTES,'UTF-8') ?>"
               type="search" placeholder="ค้นหาเมนู (ตัวแอดมิน)">
        <?php if($category_id>0){ ?><input type="hidden" name="category_id" value="<?= (int)$category_id ?>"><?php } ?>
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>

    <div class="d-flex align-items-center">
      <a href="dashboard.php" class="btn btn-primary btn-sm mr-2">ไปหน้า Dashboard</a>
      <a href="../front_store/front_store.php" class="btn btn-light btn-sm mr-2">ไปหน้าร้าน</a>
      <a href="attendance_admin.php" class="btn btn-light btn-sm">เวลาทำงาน</a>
       <a href="users_list.php" class="btn btn-ghost btn-sm mr-2">สมาชิกทั้งหมด</a>
      <a href="add_user.php" class="btn btn-ghost btn-sm mr-2">+ สร้างผู้ใช้</a>
      <span class="badge badge-user px-3 py-2 mr-2">ผู้ดูแลระบบ</span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <!-- แถบหมวดหมู่ -->
<div class="pos-card p-3 mb-3">
  <div class="d-flex align-items-center flex-wrap chips w-100">
    <div class="mr-2 text-white-50 font-weight-bold">หมวดหมู่:</div>

    <a href="adminmenu.php<?= $q!==''?('?q='.urlencode($q)) : '' ?>" class="<?= $category_id===0?'active':'' ?>">ทั้งหมด</a>

    <?php while($cat=$category_result->fetch_assoc()): ?>
      <?php $link = "adminmenu.php?category_id=".(int)$cat['category_id'].($q!==''?('&q='.urlencode($q)):''); ?>
      <a href="<?= htmlspecialchars($link,ENT_QUOTES,'UTF-8') ?>"
         class="<?= $category_id===(int)$cat['category_id']?'active':'' ?>">
         <?= htmlspecialchars($cat['category_name'],ENT_QUOTES,'UTF-8') ?>
      </a>
    <?php endwhile; ?>

    <div class="ml-auto d-flex align-items-center" style="gap:8px;">
      <a class="btn btn-sm btn-ghost" href="add_category.php">+ เพิ่มหมวดหมู่</a>
      <a class="btn btn-sm btn-ghost" href="add_menu.php">+ เพิ่มเมนู</a>
    </div>
  </div>
</div>


  <!-- Flash -->
  <?php if (isset($_GET['msg'])):
     $map = [
    'added'=>'เพิ่มเมนูเรียบร้อยแล้ว',
    'updated'=>'แก้ไขเมนูเรียบร้อยแล้ว',
    'toggled_on'=>'เปิดการขายเมนูเรียบร้อย',
    'toggled_off'=>'ปิดการขายเมนูเรียบร้อย',
    'user_added'=>'สร้างผู้ใช้ใหม่เรียบร้อยแล้ว' // <- เพิ่มบรรทัดนี้
  ];
    $text = $map[$_GET['msg']] ?? '';
    if ($text): ?>
      <div class="flash <?= $_GET['msg']==='toggled_off'?'danger':'' ?>"><?= $text ?></div>
  <?php endif; endif; ?>

  <!-- Grid การ์ดเมนู -->
  <?php if ($result && $result->num_rows > 0): ?>
  <div class="menu-grid">
    <?php while($row = $result->fetch_assoc()):
      $imageName = trim((string)$row['image']);
      $filePath  = __DIR__ . "/images/" . ($imageName ?: 'default.png');
      $imgUrl    = "images/" . (( $imageName && file_exists($filePath) ) ? $imageName : 'default.png');

      $isActive = (int)$row['is_active'] === 1;
      $cardCls  = $isActive ? 'menu-card' : 'menu-card is-off';
    ?>
      <div class="<?= $cardCls ?>">
        <img src="<?= htmlspecialchars($imgUrl,ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8') ?>">
        <span class="card-ribbon"><?= $isActive ? 'กำลังขาย' : 'ปิดการขาย' ?></span>

        <div class="meta">
          <h3><?= htmlspecialchars($row['name'],ENT_QUOTES,'UTF-8') ?></h3>

          <div class="price-pill">
            <span><?= baht($row['price']) ?></span><span>บาท</span>
          </div>

          <div class="info-line">หมวดหมู่: <?= htmlspecialchars($row['category_name'] ?? '-',ENT_QUOTES,'UTF-8') ?></div>

          <div class="badge-chip <?= $isActive ? '' : 'off' ?>">
            <?= $isActive ? 'พร้อมขาย' : 'หยุดขายชั่วคราว' ?>
          </div>
        </div>

        <div class="card-actions">
          <a class="btn btn-edit" href="edit_menu.php?id=<?= (int)$row['menu_id'] ?>">แก้ไข</a>

          <?php if ($isActive): ?>
            <form method="post" action="toggle_sale.php" style="margin:0">
              <input type="hidden" name="id" value="<?= (int)$row['menu_id'] ?>">
              <input type="hidden" name="to" value="0">
              <button type="submit" class="btn btn-toggle-on" onclick="return confirm('ปิดการขายเมนูนี้?')">ปิดการขาย</button>
            </form>
          <?php else: ?>
            <form method="post" action="toggle_sale.php" style="margin:0">
              <input type="hidden" name="id" value="<?= (int)$row['menu_id'] ?>">
              <input type="hidden" name="to" value="1">
              <button type="submit" class="btn btn-toggle-off">เปิดการขาย</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
    <div class="text-light">ไม่มีข้อมูลเมนู</div>
  <?php endif; ?>

</div>
</body>
</html>

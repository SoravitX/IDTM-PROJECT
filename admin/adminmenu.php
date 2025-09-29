<?php
// adminmenu.php — Admin UI + Show active promotions per menu (front_store theme)
declare(strict_types=1);
include_once __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

/* ---------- Detect current page for highlighting Add buttons ---------- */
$currPath  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
$currFile  = basename($currPath);
$isAddCat  = ($currFile === 'add_category.php');
$isAddMenu = ($currFile === 'add_menu.php');
// Allow forcing highlight on this page (adminmenu.php?active=addcat|addmenu)
$force = $_GET['active'] ?? '';
$isAddCat  = $isAddCat  || ($force === 'addcat');
$isAddMenu = $isAddMenu || ($force === 'addmenu');

/* ---------------- Utils ---------------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function baht($n){ return number_format((float)$n,2); }
function promo_text(array $p): string {
  $type = strtoupper((string)$p['discount_type']);
  $val  = (float)$p['discount_value'];
  $label = ($type === 'PERCENT')
    ? ('ลด '.rtrim(rtrim(number_format($val,2), '0'),'.').'%')
    : ('ลด '.baht($val).'฿');
  return $label;
}

/* ---------------- Data: Categories ---------------- */
$category_sql    = "SELECT category_id, category_name FROM categories ORDER BY category_id";
$category_result = $conn->query($category_sql);

/* ---------------- Filters ---------------- */
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$q           = trim((string)($_GET['q'] ?? ''));

/* ---------------- Menu list ---------------- */
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

/* ---------------- Active promotions (now) ---------------- */
// ITEM promotions
$itemPromosByMenu = [];
$stmt = $conn->prepare("
  SELECT pi.menu_id, p.promo_id, p.name, p.discount_type, p.discount_value
  FROM promotions p
  JOIN promotion_items pi ON pi.promo_id = p.promo_id
  WHERE p.is_active = 1
    AND p.scope = 'ITEM'
    AND p.start_at <= NOW() AND p.end_at >= NOW()
");
$stmt->execute();
$rs = $stmt->get_result();
while ($r = $rs->fetch_assoc()) {
  $mid = (int)$r['menu_id'];
  if (!isset($itemPromosByMenu[$mid])) $itemPromosByMenu[$mid] = [];
  $itemPromosByMenu[$mid][] = $r;
}
$stmt->close();

// ORDER promotions (ทั้งบิล)
$orderPromos = [];
$resOrder = $conn->query("
  SELECT promo_id, name, discount_type, discount_value
  FROM promotions
  WHERE is_active = 1
    AND scope = 'ORDER'
    AND start_at <= NOW() AND end_at >= NOW()
");
while ($r = $resOrder->fetch_assoc()) $orderPromos[] = $r;

// จำนวนนับเพื่อโชว์ UI เท่านั้น
$total_found = ($result && $result instanceof mysqli_result) ? (int)$result->num_rows : 0;
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
/* ====================== Base (ของเดิม) ====================== */
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg-grad1:#222831; --bg-grad2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --ink:#F4F7F8; --ink-muted:#CFEAED;
  --brand-900:#EEEEEE; --brand-700:#BFC6CC; --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
  --ok:#2ecc71; --danger:#e53935;
  --shadow-lg:0 22px 66px rgba(0,0,0,.55); --shadow:0 14px 32px rgba(0,0,0,.42);
}
html,body{height:100%}
body{
  margin:0; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--ink);
}
.container-fluid.pos-shell{padding:14px; max-width:1600px}
.topbar{
  position:sticky; top:0; z-index:50;
  padding:12px 16px; border-radius:14px;
  background:color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 20%);
  box-shadow:0 8px 20px rgba(0,0,0,.35); backdrop-filter: blur(6px);
}
.brand{font-weight:900; letter-spacing:.3px; color:var(--text-strong)}
.searchbox{
  background:var(--surface-2); border:1px solid color-mix(in oklab, var(--brand-700), black 24%);
  color:var(--ink); border-radius:999px; padding:.45rem .95rem; min-width:260px
}
.searchbox::placeholder{color:#9fb0ba}
.btn-ghost{
  background:linear-gradient(180deg,var(--brand-500),var(--brand-400));
  border:0; color:#062b33; font-weight:800; border-radius:10px
}
.topbar .btn-primary{
  background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800; border-radius:10px
}
.badge-user{
  background:color-mix(in oklab, var(--surface-2), white 6%); color:var(--brand-900);
  font-weight:800; border-radius:999px; border:1px solid color-mix(in oklab, var(--brand-700), black 24%)
}
.counter-pill{
  display:inline-flex; align-items:center; gap:6px;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 24%);
  border-radius:999px; padding:6px 10px; font-weight:800
}
.pos-card{
  background:color-mix(in oklab, var(--surface), white 8%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:16px; box-shadow:var(--shadow)
}
.chips a{
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; margin:0 8px 10px 0; border-radius:999px;
  border:1px solid color-mix(in oklab, var(--brand-700), black 24%);
  color:var(--text-normal); text-decoration:none; font-weight:800;
  background:color-mix(in oklab, var(--surface-2), white 6%);
  transition:filter .12s ease, transform .08s ease;
}
.chips a:hover{ filter:brightness(1.05) }
.chips a.active{
  background:linear-gradient(180deg,var(--brand-500),var(--brand-400));
  color:#062b33; border-color:#0d5a60; box-shadow:0 8px 18px rgba(0,0,0,.25)
}
.menu-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:16px; }
.menu-card{
  position:relative; border-radius:16px; overflow:hidden; display:flex; flex-direction:column;
  background:
    radial-gradient(220px 80px at 100% 0%, rgba(0,173,181,.18), transparent 60%),
    linear-gradient(180deg, color-mix(in oklab, var(--surface), white 10%), color-mix(in oklab, var(--surface-2), white 4%));
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  box-shadow:var(--shadow);
  transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.menu-card:hover{ transform:translateY(-3px); box-shadow:var(--shadow-lg); border-color:color-mix(in oklab, var(--brand-700), white 10%); }
.menu-card img{ width:100%; height:160px; object-fit:cover; display:block; background:#1a2127 }
.card-ribbon{
  position:absolute; top:10px; left:10px;
  background:color-mix(in oklab, var(--brand-500), black 20%);
  color:#04272a; font-weight:900; font-size:.75rem;
  padding:6px 10px; border-radius:999px; box-shadow:0 10px 18px rgba(0,0,0,.25);
  display:inline-flex; align-items:center; gap:6px
}
.card-ribbon .dot{ width:8px; height:8px; border-radius:50%; background:#0bd477 }
.menu-card.is-off .card-ribbon{ background:color-mix(in oklab, var(--danger), black 8%); color:#fff }
.menu-card.is-off .card-ribbon .dot{ background:#ffd6d6 }
.menu-card .meta{ padding:12px 14px 10px; }
.menu-card h3{ margin:0 0 6px; font-size:1.02rem; color:var(--brand-300); line-height:1.2; font-weight:900; text-shadow:0 0 1px rgba(0,0,0,.35); }
.price-pill{
  display:inline-flex; align-items:center; gap:6px;
  background:color-mix(in oklab, var(--surface-3), white 6%);
  color:var(--brand-900); border:1px solid color-mix(in oklab, var(--brand-700), black 22%); border-radius:999px;
  padding:6px 10px; font-weight:900;
}
.info-line{ color:var(--text-muted); margin:8px 0 6px; display:flex; align-items:center; gap:6px }
.badge-chip{
  display:inline-flex; align-items:center; gap:6px; margin-top:8px; margin-right:6px;
  background:color-mix(in oklab, var(--surface-2), white 6%); color:var(--brand-900);
  border:1px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:999px; padding:5px 10px; font-weight:800; font-size:.82rem;
}
.badge-chip.off{ background: var(--danger, #e53935); color: #fff; border-color: #c62828; }
.badge-chip.ok,.badge-chip.promo{ background: var(--ok, #2ecc71); color: #0b2a17; border-color: #1fa85a; }
.card-actions{
  margin-top:auto; display:flex; gap:10px; padding:12px;
  background:color-mix(in oklab, var(--surface-3), white 6%);
  border-top:1px solid color-mix(in oklab, var(--brand-700), black 22%);
}
.btn{ flex:0 0 auto; cursor:pointer; border:1px solid transparent; border-radius:12px; padding:9px 12px;
  font-weight:900; font-size:.92rem; box-shadow:0 6px 14px rgba(0,0,0,.25); transition:transform .08s ease, filter .12s ease; }
.btn:active{ transform:translateY(1px); }
.btn-edit{ background:linear-gradient(180deg,var(--brand-500),var(--brand-400)); color:#052a30; border:0; }
.btn-edit:hover{ filter:brightness(1.05) }
.btn-toggle-off{ background:linear-gradient(180deg,#2ecc71,#25b864); color:#052a18; border:0; }
.btn-toggle-on{  background:linear-gradient(180deg,#ff6b6b,#e94444); color:#2a0202; border:0; }
:focus-visible{outline:3px solid var(--brand-400); outline-offset:2px; border-radius:10px}
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2e3a44;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#3a4752}
*::-webkit-scrollbar-track{background:#151a20}
@media(max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} }
.topbar-actions{ gap:10px; flex-wrap:wrap; }
:root{ --ctl-h:36px; }
.input-group.input-group-sm .input-group-text{
  height:var(--ctl-h); padding:0 10px; border-radius:999px 0 0 999px !important;
  background:var(--surface-2); color:var(--brand-900);
  border:1px solid color-mix(in oklab, var(--brand-700), black 24%); line-height:1;
}
.searchbox{ height:var(--ctl-h); padding:0 14px; border-radius:0 999px 999px 0 !important; border-left:0 !important; }
.topbar .btn,.btn-ghost.btn-sm,.btn-primary.btn-sm,.btn-outline-light.btn-sm{
  height:var(--ctl-h); padding:0 14px; display:inline-flex; align-items:center; gap:6px; line-height:1; white-space:nowrap; border-radius:12px;
}
.btn-ghost{ background:linear-gradient(180deg,var(--brand-500),var(--brand-400)); border:0; color:#062b33; font-weight:800; }
.topbar .btn-primary{ height:var(--ctl-h); padding:0 14px; border-radius:12px; font-weight:800; }
.counter-pill{ height:var(--ctl-h); padding:0 12px; align-items:center; border-radius:999px; display:inline-flex; gap:6px; line-height:1; }
.counter-pill,.badge-user,.input-group-text,.searchbox{ box-shadow:0 4px 10px rgba(0,0,0,.25); }
.input-group.input-group-sm{ margin-right:8px; }
.btn-logout{
  background:linear-gradient(180deg, #e53935, #c62828); color:#fff !important; font-weight:800;
  border:1px solid #b71c1c; border-radius:12px; height:var(--ctl-h); padding:0 14px; display:inline-flex; align-items:center; gap:6px;
  box-shadow:0 4px 12px rgba(229,57,53,.35);
}
.btn-logout:hover{ filter:brightness(1.08); }

/* ================== OVERRIDE ให้ตรงกับหน้า front_store ================== */
:root{
  /* โทนเข้มน้ำเงินหน้าร้าน */
  --bg-grad1:#11161b; --bg-grad2:#141b22;
  --surface:#1a2230;  --surface-2:#192231; --surface-3:#202a3a;
  --ink:#e9eef6; --ink-muted:#b9c6d6; --text-strong:#ffffff;

  /* ฟ้าแบรนด์เดียวกัน */
  --brand-500:#3aa3ff; --brand-400:#7cbcfd; --brand-300:#a9cffd;

  --shadow:none; --shadow-lg:none;
}
body,.table,.btn,input,label,.badge{ font-size:14.5px !important; }
.topbar,.pos-card,.menu-card,.card-actions{
  background: var(--surface) !important;
  border: 1px solid rgba(255,255,255,.08) !important;
  box-shadow: none !important;
}
.menu-card .meta,
.card-actions{ background: var(--surface-2) !important; border-color: rgba(255,255,255,.10) !important; }
.menu-card{ border-radius:12px !important; background: var(--surface) !important; }
.menu-card img{ background:#10161f; }
.menu-card h3{ color:#d6e6ff !important; text-shadow:none !important; }
.price-pill{ background: var(--surface-3) !important; border:1px solid rgba(255,255,255,.12) !important; color:#eaf2ff !important; }
.info-line{ color:#9db0c4 !important; }

.chips a{ background: var(--surface-3) !important; border:1px solid rgba(255,255,255,.12) !important; color:#dce6f3 !important; }
.chips a.active{
  background: var(--brand-500) !important; color:#fff !important; border-color:#1e6acc !important; box-shadow:none !important;
}

.btn-ghost,
.btn-edit,
.topbar .btn-primary{
  background: var(--brand-500) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important;
  box-shadow:none !important;
}
.btn-ghost:hover,.btn-edit:hover,.topbar .btn-primary:hover{ filter:brightness(1.06); }

.badge-user,
.counter-pill,
.badge-chip{
  background: var(--surface-3) !important;
  color: #eaf2ff !important;
  border: 1px solid rgba(255,255,255,.12) !important;
  box-shadow: none !important;
}

/* ชิปสถานะ/โปร ให้คุมโทนเดียวกัน */
.badge-chip.ok{ background:#22c55e !important; color:#06240f !important; border-color:#15803d !important; }
.badge-chip.off{ background:#e53935 !important; border-color:#c62828 !important; color:#fff !important; }
.badge-chip.promo{ background:#22c55e !important; color:#06240f !important; border-color:#15803d !important; }

.card-ribbon{
  background: var(--brand-500) !important; color:#061a2a !important; box-shadow:none !important;
}
.menu-card.is-off .card-ribbon{ background:#e53935 !important; color:#fff !important; }

.btn-toggle-off{ background: linear-gradient(180deg,#22c55e,#16a34a) !important; color:#052a18 !important; border:0 !important; }
.btn-toggle-on{  background: linear-gradient(180deg,#ff6b6b,#e94444) !important; color:#2a0202 !important; border:0 !important; }
</style>
</head>
<body>
<div class="container-fluid pos-shell">

  <!-- Topbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3"><i class="bi bi-speedometer2"></i> PSU Blue Cafe • Admin</h4>

      <form class="form-inline" method="get" action="adminmenu.php" role="search" aria-label="ค้นหาเมนู">
        <div class="input-group input-group-sm mr-2">
          <div class="input-group-prepend">
            <span class="input-group-text">
              <i class="bi bi-search"></i>
            </span>
          </div>
          <input name="q" class="form-control form-control-sm searchbox"
                 value="<?= h($q) ?>" type="search" placeholder="ค้นหาเมนู (ตัวแอดมิน)" aria-label="Search">
          <?php if($category_id>0){ ?><input type="hidden" name="category_id" value="<?= (int)$category_id ?>"><?php } ?>
        </div>
        <button class="btn btn-sm btn-ghost" type="submit"><i class="bi bi-arrow-right-circle"></i> ค้นหา</button>
      </form>
    </div>
    <div class="d-flex align-items-center topbar-actions">
      <span class="counter-pill"><i class="bi bi-list-ul"></i> พบ <?= (int)$total_found ?> รายการ</span>
      <a href="dashboard.php" class="btn btn-primary btn-sm"><i class="bi bi-graph-up"></i> Dashboard</a>
      <a href="order.php" class="btn btn-ghost btn-sm">
  <i class="bi bi-receipt"></i> ออเดอร์
</a>

      <a href="users_list.php" class="btn btn-ghost btn-sm"><i class="bi bi-people"></i> สมาชิกทั้งหมด</a>

      <!-- ✅ ปุ่ม Categories -->
      <a href="categories.php" class="btn btn-ghost btn-sm">
        <i class="bi bi-columns-gap"></i> หมวดหมู่
      </a>

      <a href="promo_create.php" class="btn btn-ghost btn-sm"><i class="bi bi-stars"></i> สร้างโปรโมชัน</a>
      <a href="recipes.php" class="btn btn-ghost btn-sm"><i class="bi bi-journal-text"></i> สูตรเมนู</a>

      <span class="badge badge-user px-3 py-2"><i class="bi bi-shield-lock"></i> ผู้ดูแลระบบ</span>
      <a class="btn btn-sm btn-logout" href="../logout.php">
        <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
      </a>
    </div>
  </div>

  <!-- แถบหมวดหมู่ -->
  <div class="pos-card p-3 mb-3">
    <div class="d-flex align-items-center flex-wrap chips w-100">
      <div class="mr-2 text-white-50 font-weight-bold"><i class="bi bi-columns-gap"></i> หมวดหมู่:</div>

      <a href="adminmenu.php<?= $q!==''?('?q='.urlencode($q)) : '' ?>" class="<?= $category_id===0?'active':'' ?>">
        <i class="bi bi-grid-3x3-gap"></i> ทั้งหมด
      </a>

      <?php while($cat=$category_result->fetch_assoc()): ?>
        <?php $link = "adminmenu.php?category_id=".(int)$cat['category_id'].($q!==''?('&q='.urlencode($q)):''); ?>
        <a href="<?= h($link) ?>" class="<?= $category_id===(int)$cat['category_id']?'active':'' ?>">
           <i class="bi bi-tag"></i> <?= h($cat['category_name']) ?>
        </a>
      <?php endwhile; ?>

      <div class="ml-auto d-flex align-items-center" style="gap:8px;">
        <a class="btn btn-sm <?= $isAddCat ? 'btn-primary' : 'btn-ghost' ?>"
           href="add_category.php" <?= $isAddCat ? 'aria-current="page"' : '' ?>>
          <i class="bi bi-plus-circle"></i> เพิ่มหมวดหมู่
        </a>
        <a class="btn btn-sm <?= $isAddMenu ? 'btn-primary' : 'btn-ghost' ?>"
           href="add_menu.php" <?= $isAddMenu ? 'aria-current="page"' : '' ?>>
          <i class="bi bi-plus-square"></i> เพิ่มเมนู
        </a>
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
      'user_added'=>'สร้างผู้ใช้ใหม่เรียบร้อยแล้ว'
     ];
     $text = $map[$_GET['msg']] ?? '';
     if ($text): ?>
      <div class="alert mb-3" style="background:rgba(58,163,255,.16); color:#dff6ff; border:1px solid rgba(58,163,255,.35); border-radius:12px">
        <i class="bi bi-check2-circle"></i> <?= h($text) ?>
      </div>
  <?php endif; endif; ?>

  <!-- Grid เมนู + โปรโมชัน -->
  <?php if ($result && $result->num_rows > 0): ?>
  <div class="menu-grid">
    <?php while($row = $result->fetch_assoc()):
      $imageName = trim((string)$row['image']);
      $filePath  = __DIR__ . "/images/" . ($imageName ?: 'default.png');
      $imgUrl    = "images/" . (( $imageName && file_exists($filePath) ) ? $imageName : 'default.png');

      $isActive = (int)$row['is_active'] === 1;
      $cardCls  = $isActive ? 'menu-card' : 'menu-card is-off';

      $mid = (int)$row['menu_id'];
      $thisItemPromos = $itemPromosByMenu[$mid] ?? [];
    ?>
      <div class="<?= $cardCls ?>">
        <img src="<?= h($imgUrl) ?>" alt="<?= h($row['name']) ?>" loading="lazy">
        <span class="card-ribbon">
          <span class="dot"></span>
          <?= $isActive ? 'กำลังขาย' : 'ปิดการขาย' ?>
        </span>

        <div class="meta">
          <h3 title="<?= h($row['name']) ?>"><i class="bi bi-cup-hot"></i> <?= h($row['name']) ?></h3>

          <div class="price-pill" title="ราคาเต็ม">
            <i class="bi bi-cash-coin"></i> <span><?= baht($row['price']) ?></span><span>&nbsp;บาท</span>
          </div>

          <div class="info-line"><i class="bi bi-folder2-open"></i> หมวดหมู่: <?= h($row['category_name'] ?? '-') ?></div>

          <div class="d-flex flex-wrap">
            <div class="badge-chip <?= $isActive ? 'ok' : 'off' ?>">
              <i class="bi <?= $isActive ? 'bi-check-circle' : 'bi-pause-circle' ?>"></i>
              <?= $isActive ? 'พร้อมขาย' : 'หยุดขายชั่วคราว' ?>
            </div>

            <?php if (!empty($thisItemPromos)): ?>
              <?php foreach ($thisItemPromos as $p): ?>
                <div class="badge-chip promo" title="โปรเฉพาะเมนู">
                  <i class="bi bi-stars"></i>
                  โปรเมนู: <?= h($p['name']) ?> • <?= h(promo_text($p)) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($orderPromos)): ?>
              <?php foreach ($orderPromos as $op): ?>
                <div class="badge-chip" title="โปรทั้งบิล">
                  <i class="bi bi-receipt"></i>
                  โปรทั้งบิล: <?= h($op['name']) ?> • <?= h(promo_text($op)) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="card-actions">
          <a class="btn btn-edit" href="edit_menu.php?id=<?= (int)$row['menu_id'] ?>">
            <i class="bi bi-pencil-square"></i> แก้ไข
          </a>

          <?php if ($isActive): ?>
            <form method="post" action="toggle_sale.php" style="margin:0">
              <input type="hidden" name="id" value="<?= (int)$row['menu_id'] ?>">
              <input type="hidden" name="to" value="0">
              <button type="submit" class="btn btn-toggle-on" onclick="return confirm('ปิดการขายเมนูนี้?')">
                <i class="bi bi-toggle2-off"></i> ปิดการขาย
              </button>
            </form>
          <?php else: ?>
            <form method="post" action="toggle_sale.php" style="margin:0">
              <input type="hidden" name="id" value="<?= (int)$row['menu_id'] ?>">
              <input type="hidden" name="to" value="1">
              <button type="submit" class="btn btn-toggle-off">
                <i class="bi bi-toggle2-on"></i> เปิดการขาย
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
  <?php else: ?>
    <div class="text-light"><i class="bi bi-emoji-neutral"></i> ไม่มีข้อมูลเมนู</div>
  <?php endif; ?>

</div>

<!-- UX: กด / เพื่อโฟกัสช่องค้นหา -->
<script>
document.addEventListener('keydown', (e)=>{
  if(e.key === '/'){
    const box = document.querySelector('input[name="q"]');
    if(box){ e.preventDefault(); box.focus(); box.select(); }
  }
});
</script>
</body>
</html>

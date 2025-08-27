<?php
// front_store/front_store.php — POS UI สวยงาม โทนสี PSU + การ์ดเมนูขนาดเล็ก + ตะกร้า sticky
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function money_fmt($n){ return number_format((float)$n, 2); }
/** คีย์ตะกร้า: menu_id::md5(trim(note)) เพื่อแยกแถวตามรายละเอียด */
function cart_key(int $menu_id, string $note): string { return $menu_id.'::'.md5(trim($note)); }
/** escape key สำหรับ name="" */
function safe_key(string $k): string { return htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); }

/* โครงสร้างตะกร้า:
 * $_SESSION['cart'][<key>] = ['menu_id','name','price','qty','image','note']
 */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ---------- Actions ---------- */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$success_msg = '';

if ($action === 'add') {
  $menu_id = (int)($_POST['menu_id'] ?? 0);
  $qty     = max(1, (int)$_POST['qty'] ?? 1);
  $note    = trim((string)($_POST['note'] ?? ''));
  $isEdit  = isset($_POST['edit']) && (int)$_POST['edit'] === 1;
  $old_key = (string)($_POST['old_key'] ?? '');

  $stmt = $conn->prepare("SELECT menu_id, name, price, image FROM menu WHERE menu_id=?");
  $stmt->bind_param("i", $menu_id);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($item) {
    $new_key = cart_key($menu_id, $note);

    if ($isEdit) {
      if ($old_key !== '' && isset($_SESSION['cart'][$old_key])) unset($_SESSION['cart'][$old_key]);
      if (isset($_SESSION['cart'][$new_key])) {
        $_SESSION['cart'][$new_key]['qty'] += $qty;
        $_SESSION['cart'][$new_key]['note'] = $note;
      } else {
        $_SESSION['cart'][$new_key] = [
          'menu_id'=>$menu_id,'name'=>$item['name'],'price'=>(float)$item['price'],
          'qty'=>$qty,'image'=>(string)$item['image'],'note'=>$note
        ];
      }
    } else {
      if (isset($_SESSION['cart'][$new_key])) $_SESSION['cart'][$new_key]['qty'] += $qty;
      else {
        $_SESSION['cart'][$new_key] = [
          'menu_id'=>$menu_id,'name'=>$item['name'],'price'=>(float)$item['price'],
          'qty'=>$qty,'image'=>(string)$item['image'],'note'=>$note
        ];
      }
    }
  }
}

if ($action === 'update') {
  foreach ($_POST['qty'] ?? [] as $key=>$q) {
    $q = max(0, (int)$q);
    if (isset($_SESSION['cart'][$key])) {
      if ($q===0) unset($_SESSION['cart'][$key]);
      else $_SESSION['cart'][$key]['qty'] = $q;
    }
  }
}

if ($action === 'remove') {
  $key = (string)($_GET['key'] ?? '');
  if ($key !== '' && isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
}

if ($action === 'clear') {
  $_SESSION['cart'] = [];
}

/* ----- CHECKOUT: บันทึก DB แล้ว “โชว์ข้อความสำเร็จ + เลขออเดอร์” ----- */
if ($action === 'checkout' && !empty($_SESSION['cart'])) {
  $total = 0.00;
  foreach ($_SESSION['cart'] as $row) $total += ((float)$row['price']) * ((int)$row['qty']);

  $stmt = $conn->prepare("
    INSERT INTO orders (user_id, order_time, status, total_price)
    VALUES (?, NOW(), 'pending', ?)
  ");
  $stmt->bind_param("id", $_SESSION['uid'], $total);
  $stmt->execute();
  $order_id = $stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("
    INSERT INTO order_details (order_id, menu_id, promo_id, quantity, note, total_price)
    VALUES (?, ?, NULL, ?, ?, ?)
  ");
  foreach ($_SESSION['cart'] as $row) {
    $line = ((int)$row['qty']) * ((float)$row['price']);

    $stmt->bind_param("iiisd", $order_id, $row['menu_id'], $row['qty'], $row['note'], $line);
    $stmt->execute();
  }
  $stmt->close();

  $_SESSION['cart'] = [];
  $success_msg = "สั่งออเดอร์แล้ว! เลขที่ออเดอร์ #{$order_id} — ไปดูรายละเอียดที่หน้า Check Out ได้";
}

/* ---------- Data ---------- */
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$keyword     = trim((string)($_GET['q'] ?? ''));

$cats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");

$sql = "SELECT m.menu_id, m.name, m.price, m.image, c.category_name
        FROM menu m LEFT JOIN categories c ON m.category_id=c.category_id
        WHERE m.is_active = 1";

$params=[]; $types='';
if ($category_id>0) { $sql.=" AND m.category_id=?"; $types.='i'; $params[]=$category_id; }
if ($keyword!=='')  { $sql.=" AND m.name LIKE ?";   $types.='s'; $params[]='%'.$keyword.'%'; }
$sql .= " ORDER BY m.menu_id";

if ($types!=='') {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute(); $menus = $stmt->get_result(); $stmt->close();
} else {
  $menus = $conn->query($sql);
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>PSU Blue Cafe • Front Store</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
/* -------- Theme (PSU palette) -------- */
:root{
  --psu-deep-blue:#0D4071;    /* ink */
  --psu-ocean-blue:#4173BD;   /* ink2 */
  --psu-andaman:#0094B3;      /* accent bg */
  --psu-sky:#29ABE2;          /* primary accent */
  --psu-river:#4EC5E0;        /* accent light */
  --psu-sritrang:#BBB4D8;     /* soft border */

  --bg-grad1: var(--psu-deep-blue);
  --bg-grad2: var(--psu-ocean-blue);
  --ink:#0b2746;
  --shadow:0 14px 32px rgba(0,0,0,.24);
  --ring:#7dd3fc;
}

html,body{height:100%}
body{
  background:linear-gradient(135deg, var(--bg-grad1), var(--bg-grad2));
  color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}

/* -------- Layout shell -------- */
.pos-shell{padding:12px; max-width:1600px; margin:0 auto;}
.topbar{
  position:sticky; top:0; z-index:50;
  padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25);
  box-shadow:0 8px 20px rgba(0,0,0,.18);
}
.brand{font-weight:900; letter-spacing:.3px}

/* Button styles */
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}
.btn-ghost:hover{background:var(--psu-sky); color:#002b4a}
.btn-primary, .btn-success{font-weight:800}

/* Chips (categories) */
.pos-card{background:rgba(255,255,255,.08); border:1px solid var(--psu-sritrang);
  border-radius:16px; box-shadow:var(--shadow);}
.chips a{
  display:inline-flex; align-items:center; gap:6px;
  padding:7px 14px; margin:0 8px 10px 0; border-radius:999px;
  border:1px solid var(--psu-ocean-blue); color:#fff; text-decoration:none; font-weight:700;
  background:rgba(255,255,255,.05);
}
.chips a.active{ background:linear-gradient(180deg, var(--psu-sky), var(--psu-river));
  color:#062d4f; border-color:#073c62; box-shadow:0 8px 18px rgba(0,0,0,.15) }
.chips a:hover{ transform:translateY(-1px) }

/* Search */
.searchbox{
  background:#fff; border:2px solid var(--psu-ocean-blue); color:#000;
  border-radius:999px; padding:.4rem .9rem; min-width:260px;
}
.searchbox:focus{ box-shadow:0 0 0 .2rem rgba(41,171,226,.35) }

/* -------- Mini menu cards grid -------- */
.menu-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:12px; padding:12px; }
.product-mini{
  background:#fff; border:1px solid #e3ecff; border-radius:14px; overflow:hidden;
  color:inherit; text-decoration:none; display:flex; flex-direction:column; height:100%;
  transition:transform .12s, box-shadow .12s, border-color .12s;
}
.product-mini:focus, .product-mini:hover{
  transform:translateY(-2px); border-color:#bed7ff; box-shadow:0 10px 20px rgba(0,0,0,.16);
  outline:none;
}
.product-mini .thumb{ width:100%; height:92px; object-fit:cover; background:#eaf4ff; }
.product-mini .meta{ padding:9px 10px 11px }
.product-mini .pname{
  font-weight:900; color:var(--psu-deep-blue); line-height:1.15; font-size:.97rem;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
  min-height:2.3em;
}
.product-mini .row2{ display:flex; align-items:center; justify-content:space-between; margin-top:6px; }
.product-mini .pprice{ font-weight:900; color:var(--psu-sky); font-size:1.02rem; letter-spacing:.2px }
.product-mini .quick{
  font-size:.8rem; font-weight:800; padding:5px 10px; border-radius:999px;
  background:var(--psu-andaman); border:1px solid #0a3e62; color:#fff;
}
.product-mini .quick:hover{ background:var(--psu-sky); color:#002a48 }

/* -------- Cart (sticky) -------- */
.cart{ position:sticky; top:82px; }
.table-cart{ color:#0b2746; background:#fff; border-radius:12px; overflow:hidden }
.table-cart thead th{
  background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; font-weight:800;
}
.table-cart tbody tr:nth-child(odd){ background:#fbfdff }
.table-cart td,.table-cart th{ border-color:#e7eefc!important; }
.note-badge{
  display:inline-block; font-size:.78rem; font-weight:700;
  background:#eef6ff; color:#07406f; border:1px dashed #cfe2ff; border-radius:8px; padding:2px 6px;
}
.cart-footer{
  background:linear-gradient(180deg, var(--psu-ocean-blue), var(--psu-deep-blue));
  color:#fff; border-top:1px solid #0D4071; padding:10px 12px; border-radius:0 0 14px 14px;
}
.total-tag{ font-size:1.25rem; font-weight:900; color:var(--psu-river) }

/* Toast */
.alert-ok{ background:#2e7d32; color:#fff; border:none }
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }

/* Focus ring helper */
:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }

/* Scrollbar (WebKit) ให้ดู POS มากขึ้น */
*::-webkit-scrollbar{ width:10px; height:10px }
*::-webkit-scrollbar-thumb{ background:#2b568a; border-radius:10px }
*::-webkit-scrollbar-thumb:hover{ background:#2f6db5 }
*::-webkit-scrollbar-track{ background:rgba(255,255,255,.08) }

/* Responsive fine-tune */
@media (max-width:420px){
  .menu-grid{ gap:10px; padding:10px }
}
</style>
</head>
<body>
<div class="container-fluid pos-shell">

  <!-- Top bar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe</h4>
      <form class="form-inline" method="get" action="front_store.php">
        <input name="q" value="<?= htmlspecialchars($keyword,ENT_QUOTES,'UTF-8') ?>"
               class="form-control form-control-sm searchbox mr-2"
               type="search" placeholder="ค้นหารายการ (กด / เพื่อค้นหา)">
        <?php if($category_id>0){ ?><input type="hidden" name="category_id" value="<?= (int)$category_id ?>"><?php } ?>
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>
    <div>
      <span class="badge badge-user p-2 mr-2">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES,'UTF-8') ?></span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <?php if ($success_msg): ?>
    <div class="alert alert-ok pos-card p-3 mb-3">
      <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
      &nbsp;&nbsp;<a class="btn btn-light btn-sm" href="checkout.php">ไปหน้า Check Out</a>
    </div>
  <?php endif; ?>

  <!-- CHIPS -->
  <div class="pos-card p-3 mb-3">
    <div class="d-flex align-items-center flex-wrap chips">
      <div class="mr-2 text-white-50 font-weight-bold">หมวดหมู่:</div>
      <a href="front_store.php<?= $keyword!==''?('?q='.urlencode($keyword)) : '' ?>" class="<?= $category_id===0?'active':'' ?>">ทั้งหมด</a>
      <?php while($c=$cats->fetch_assoc()):
        $link = "front_store.php?category_id=".(int)$c['category_id'].($keyword!==''?('&q='.urlencode($keyword)):''); ?>
        <a href="<?= htmlspecialchars($link,ENT_QUOTES,'UTF-8') ?>" class="<?= $category_id===(int)$c['category_id']?'active':'' ?>">
          <?= htmlspecialchars($c['category_name'],ENT_QUOTES,'UTF-8') ?>
        </a>
      <?php endwhile; ?>
    </div>
  </div>

  <div class="row">
    <!-- เมนู (การ์ดขนาดเล็ก อ่านง่าย) -->
    <div class="col-lg-9 mb-3">
      <div class="pos-card">
        <?php if($menus && $menus->num_rows>0): ?>
          <div class="menu-grid">
            <?php while($m=$menus->fetch_assoc()):
              $img = trim((string)$m['image']);
              $imgPathFs = __DIR__ . "/../admin/images/" . ($img !== '' ? $img : "default.png");
              $imgSrc    = "../admin/images/" . ($img !== '' ? $img : "default.png");
              if (!file_exists($imgPathFs)) $imgSrc = "https://via.placeholder.com/600x400?text=No+Image";
            ?>
              <a class="product-mini" href="menu_detail.php?id=<?= (int)$m['menu_id'] ?>" tabindex="0">
                <img class="thumb" src="<?= htmlspecialchars($imgSrc,ENT_QUOTES,'UTF-8') ?>" alt="">
                <div class="meta">
                  <div class="pname"><?= htmlspecialchars($m['name'],ENT_QUOTES,'UTF-8') ?></div>
                  <div class="row2">
                    <div class="pprice"><?= money_fmt($m['price']) ?> ฿</div>
                    <span class="quick">เลือก</span>
                  </div>
                </div>
              </a>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="p-3">
            <div class="alert alert-warning m-0">ไม่พบสินค้า</div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ออเดอร์ (ตะกร้า sticky) -->
    <div class="col-lg-3 mb-3">
      <div class="pos-card cart">
        <div class="d-flex align-items-center justify-content-between p-3 pt-3 pb-0">
          <div class="h5 mb-0 font-weight-bold">ออเดอร์</div>
          <a class="btn btn-sm btn-outline-light" href="front_store.php?action=clear"
             onclick="return confirm('ล้างออเดอร์ทั้งหมด?');">ล้าง</a>
        </div>
        <hr class="my-2" style="border-color:rgba(255,255,255,.25)">
        <div class="p-2 pt-0">
        <?php if(!empty($_SESSION['cart'])): ?>
          <form method="post" id="frmCart">
            <input type="hidden" name="action" value="update">
            <div class="table-responsive">
              <table class="table table-sm table-cart">
                <thead>
                  <tr><th>รายการ</th><th class="text-right">ราคา</th>
                      <th class="text-center" style="width:86px;">จำนวน</th>
                      <th class="text-right">รวม</th><th></th></tr>
                </thead>
                <tbody>
                <?php $sum=0.0; foreach($_SESSION['cart'] as $key=>$it):
                  $line=$it['price']*$it['qty']; $sum+=$line; ?>
                  <tr>
                    <td class="align-middle">
                      <div class="font-weight-bold" style="color:#0D4071"><?= htmlspecialchars($it['name'],ENT_QUOTES,'UTF-8') ?></div>
                      <?php if(!empty($it['note'])): ?>
                        <div class="note-badge mt-1"><?= htmlspecialchars($it['note'],ENT_QUOTES,'UTF-8') ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-right align-middle"><?= money_fmt($it['price']) ?></td>
                    <td class="text-center align-middle">
                      <input class="form-control form-control-sm" type="number" name="qty[<?= safe_key($key) ?>]" value="<?= (int)$it['qty'] ?>" min="0">
                    </td>
                    <td class="text-right align-middle"><?= money_fmt($line) ?></td>
                    <td class="text-right align-middle">
                      <div class="btn-group btn-group-sm">
                        <a class="btn btn-outline-primary" href="menu_detail.php?id=<?= (int)$it['menu_id'] ?>&edit=1&key=<?= urlencode($key) ?>">แก้ไข</a>
                        <a class="btn btn-outline-danger" href="front_store.php?action=remove&key=<?= urlencode($key) ?>" onclick="return confirm('ลบรายการนี้?');">ลบ</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </form>

          <?php $sum = $sum ?? 0.0; ?>
          </div>
          <div class="cart-footer d-flex align-items-center justify-content-between">
            <div class="h6 mb-0">ยอดรวม</div>
            <div class="total-tag"><?= money_fmt($sum) ?> ฿</div>
          </div>
          <div class="p-3">
            <div class="d-flex">
              <button class="btn btn-light mr-2" form="frmCart" style="font-weight:800">อัปเดตจำนวน</button>
              <form method="post" class="m-0 flex-fill">
                <input type="hidden" name="action" value="checkout">
                <button class="btn btn-success btn-block" id="btnCheckout" style="font-weight:900; letter-spacing:.2px">
                  สั่งออเดอร์ (F2)
                </button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="px-3 pb-3 text-light" style="opacity:.9">ยังไม่มีสินค้าในออเดอร์</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Hotkeys -->
<script>
document.addEventListener('keydown', function(e){
  if (e.key === '/') {
    const q = document.querySelector('input[name="q"]');
    if (q) { q.focus(); q.select(); e.preventDefault(); }
  }
  if (e.key === 'F2') {
    const btn = document.getElementById('btnCheckout');
    if (btn) { e.preventDefault(); btn.click(); }
  }
});
</script>

<!-- Toast zone + เสียงแจ้งเตือน -->
<div id="toast-zone" style="position:fixed; right:16px; bottom:16px; z-index:9999;"></div>
<audio id="ding" preload="auto">
  <source src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" type="audio/ogg">
</audio>

<script>
let lastSince = '';
let knownStatus = {};

function showToast(text, style='info'){
  const id = 't' + Date.now();
  const bg = style==='success' ? '#28a745' : (style==='danger' ? '#dc3545' : '#007bff');
  const el = document.createElement('div');
  el.id = id;
  el.style.cssText =
    `min-width:260px;margin-top:8px;background:${bg};color:#fff;padding:12px 14px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.2);font-weight:700`;
  el.textContent = text;
  document.getElementById('toast-zone').appendChild(el);
  setTimeout(()=> el.remove(), 4000);
}

async function poll(){
  try{
    // ✅ เรียกเฉพาะออเดอร์ของผู้ใช้คนนี้ ด้วย mine=1
    const base = '../api/orders_feed.php?mine=1';
    const qs   = lastSince ? ('&since='+encodeURIComponent(lastSince)) : '';
    const r = await fetch(base+qs, {cache:'no-store'});
    if(!r.ok) throw new Error('HTTP '+r.status);
    const data = await r.json();
    if(!data.ok) return;

    // เซ็ต baseline เวลาในรอบแรก
    if (!lastSince && data.now) lastSince = data.now;

    if(data.orders && data.orders.length){
      // เลื่อนไป updated_at ล่าสุดของรอบนี้
      lastSince = data.orders[data.orders.length - 1].updated_at;

      for(const o of data.orders){
        const id = o.order_id, st = o.status;
        const prev = knownStatus[id];
        knownStatus[id] = st;

        // แจ้งเฉพาะการเปลี่ยนแปลง (กันแจ้งรัวตอนเริ่ม)
        if (prev && prev !== st){
          if (st === 'ready'){
            showToast(`ออเดอร์ของคุณ #${id} เสร็จแล้ว!`, 'success');
            document.getElementById('ding')?.play().catch(()=>{});
          } else if (st === 'canceled'){
            showToast(`ออเดอร์ของคุณ #${id} ถูกยกเลิก`, 'danger');
          } else {
            showToast(`ออเดอร์ของคุณ #${id} → ${st}`);
          }
        }
      }
    }
  }catch(e){
    // เงียบได้
  }finally{
    setTimeout(poll, 1500); // โพลทุก ~1.5s ให้ลื่น ๆ
  }
}
window.addEventListener('load', poll);
</script>

</body>
</html>

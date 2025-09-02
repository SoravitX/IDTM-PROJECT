<?php
// front_store/front_store.php — POS UI + modal popup menu_detail (PSU tone) + Voice Ready Notification
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function money_fmt($n){ return number_format((float)$n, 2); }
function cart_key(int $menu_id, string $note): string { return $menu_id.'::'.md5(trim($note)); }
function safe_key(string $k): string { return htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); }

/* ---------- Cart session ---------- */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ---------- Actions ---------- */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$success_msg = '';

if ($action === 'add') {
  $menu_id = (int)($_POST['menu_id'] ?? 0);
  $qty     = max(1, (int)($_POST['qty'] ?? 1));
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
        $_SESSION['cart'][$new_key]['qty']  += $qty;
        $_SESSION['cart'][$new_key]['note']  = $note;
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

  // ตอบ JSON เมื่อมาจาก AJAX
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok'    => true,
      'count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0,
    ]);
    exit;
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

if ($action === 'clear') { $_SESSION['cart'] = []; }

/* ----- CHECKOUT ----- */
if ($action === 'checkout' && !empty($_SESSION['cart'])) {
  $total = 0.00;
  foreach ($_SESSION['cart'] as $row) $total += ((float)$row['price']) * ((int)$row['qty']);

  $stmt = $conn->prepare("INSERT INTO orders (user_id, order_time, status, total_price)
                          VALUES (?, NOW(), 'pending', ?)");
  $stmt->bind_param("id", $_SESSION['uid'], $total);
  $stmt->execute();
  $order_id = $stmt->insert_id;
  $stmt->close();

  $stmt = $conn->prepare("INSERT INTO order_details (order_id, menu_id, promo_id, quantity, note, total_price)
                          VALUES (?, ?, NULL, ?, ?, ?)");
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
<title>PSU Blue Cafe • Menu</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
.topbar-actions { gap: 8px; }
.topbar .btn-primary { background: linear-gradient(180deg, #3aa3ff, #1f7ee8); border-color: #1669c9; font-weight: 800; }
@media (max-width: 576px){ .topbar { flex-wrap: wrap; gap: 8px; } .topbar-actions { width: 100%; justify-content: flex-end; } }

/* ===== POS Popup Modal ===== */
.psu-modal{ position:fixed; inset:0; display:none; z-index:1050; }
.psu-modal.is-open{ display:block; }
.psu-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.45); backdrop-filter: blur(2px); }
.psu-modal__dialog{ position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(1020px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:20px; box-shadow:0 22px 66px rgba(0,0,0,.45); border:1px solid #cfe3ff; }
.psu-modal__body{ padding:0; }
.psu-modal__close{ position:absolute; right:12px; top:8px; border:0; background:transparent; font-size:32px; font-weight:900; line-height:1; cursor:pointer; color:#08345c; }

/* ===== Theme (PSU) ===== */
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2; --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --bg-grad1: var(--psu-deep-blue); --bg-grad2: var(--psu-ocean-blue);
  --ink:#0b2746; --shadow:0 14px 32px rgba(0,0,0,.24); --ring:#7dd3fc;
}
html,body{height:100%}
body{ background:linear-gradient(135deg, var(--bg-grad1), var(--bg-grad2)); color:#fff; font-family:"Segoe UI",Tahoma,Arial,sans-serif; }

/* Layout */
.pos-shell{padding:12px; max-width:1600px; margin:0 auto;}
.topbar{ position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px; background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18); }
.brand{font-weight:900; letter-spacing:.3px}

/* Buttons */
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}
.btn-ghost:hover{background:var(--psu-sky); color:#002b4a}
.btn-primary, .btn-success{font-weight:800}

/* Chips & card */
.pos-card{background:rgba(255,255,255,.08); border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:var(--shadow);}
.chips a{ display:inline-flex; align-items:center; gap:6px; padding:7px 14px; margin:0 8px 10px 0; border-radius:999px; border:1px solid var(--psu-ocean-blue); color:#fff; text-decoration:none; font-weight:700; background:rgba(255,255,255,.05); }
.chips a.active{ background:linear-gradient(180deg, var(--psu-sky), var(--psu-river)); color:#062d4f; border-color:#073c62; box-shadow:0 8px 18px rgba(0,0,0,.15) }
.chips a:hover{ transform:translateY(-1px) }

/* Search */
.searchbox{ background:#fff; border:2px solid var(--psu-ocean-blue); color:#000; border-radius:999px; padding:.4rem .9rem; min-width:260px; }
.searchbox:focus{ box-shadow:0 0 0 .2rem rgba(41,171,226,.35) }

/* Mini menu cards */
.menu-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:12px; padding:12px; }
.product-mini{ background:#fff; border:1px solid #e3ecff; border-radius:14px; overflow:hidden; color:inherit; text-decoration:none; display:flex; flex-direction:column; height:100%; transition:transform .12s, box-shadow .12s, border-color .12s; }
.product-mini:focus, .product-mini:hover{ transform:translateY(-2px); border-color:#bed7ff; box-shadow:0 10px 20px rgba(0,0,0,.16); outline:none; }
.product-mini .thumb{ width:100%; height:92px; object-fit:cover; background:#eaf4ff; }
.product-mini .meta{ padding:9px 10px 11px }
.product-mini .pname{ font-weight:900; color:#0D4071; line-height:1.15; font-size:.97rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; min-height:2.3em; }
.product-mini .row2{ display:flex; align-items:center; justify-content:space-between; margin-top:6px; }
.product-mini .pprice{ font-weight:900; color:#29ABE2; font-size:1.02rem; letter-spacing:.2px }
.product-mini .quick{ font-size:.8rem; font-weight:800; padding:5px 10px; border-radius:999px; background:#0094B3; border:1px solid #0a3e62; color:#fff; }
.product-mini .quick:hover{ background:#29ABE2; color:#002a48 }

/* Cart */
.cart{ position:sticky; top:82px; }
.table-cart{ color:#0b2746; background:#fff; border-radius:12px; overflow:hidden; table-layout:auto; }
.table-cart thead th{ background:#f5f9ff; color:#06345c; border-bottom:2px solid #e7eefc; font-weight:800; }
.table-cart td,.table-cart th{ border-color:#e7eefc!important; }
.table-cart thead th:first-child, .table-cart tbody td:first-child{ width:58%; }

/* Notes in cart */
.note-list{ display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.note-pill{ display:inline-flex; align-items:center; background:#eef6ff; border:1px solid #cfe2ff; border-radius:999px; padding:4px 10px; font-size:.82rem; font-weight:800; }
.note-pill .k{ color:#194bd6; margin-right:6px; } .note-pill .v{ color:#0D4071; }

/* divider rows */
.table-cart tbody tr:not(:last-child) td{ border-bottom:2px dashed #0066ff !important; }

.cart-footer{ background:linear-gradient(180deg, var(--psu-ocean-blue), var(--psu-deep-blue)); color:#fff; border-top:1px solid #0D4071; padding:10px 12px; border-radius:0 0 14px 14px; }
.total-tag{ font-size:1.25rem; font-weight:900; color:#4EC5E0 }

.alert-ok{ background:#2e7d32; color:#fff; border:none }
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }

:focus-visible{ outline:3px solid var(--ring); outline-offset:2px; border-radius:10px }

/* Scrollbar */
*::-webkit-scrollbar{ width:10px; height:10px }
*::-webkit-scrollbar-thumb{ background:#2b568a; border-radius:10px }
*::-webkit-scrollbar-thumb:hover{ background:#2f6db5 }
*::-webkit-scrollbar-track{ background:rgba(255,255,255,.08) }

.voice-toggle{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.25); font-weight:800; }
</style>
</head>
<body>
<div class="container-fluid pos-shell">

  <!-- Top bar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • Menu</h4>

      <form class="form-inline" method="get" action="front_store.php">
        <input name="q" value="<?= htmlspecialchars($keyword,ENT_QUOTES,'UTF-8') ?>" class="form-control form-control-sm searchbox mr-2" type="search" placeholder="ค้นหารายการ (กด / เพื่อค้นหา)">
        <?php if($category_id>0){ ?><input type="hidden" name="category_id" value="<?= (int)$category_id ?>"><?php } ?>
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>

    <!-- actions (right) -->
    <div class="d-flex align-items-center topbar-actions">
      <label class="voice-toggle mr-2 mb-0">
        <input type="checkbox" id="voiceSwitch" class="mr-1">
        เสียงแจ้งเตือน
      </label>

      <a href="checkout.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">Check Order</a>
      <a href="../SelectRole/role.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">ตําเเหน่ง</a>
      <span class="badge badge-user px-3 py-2 mr-2">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES,'UTF-8') ?></span>
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
    <!-- เมนู -->
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
              <a class="product-mini" href="menu_detail.php?id=<?= (int)$m['menu_id'] ?>" data-id="<?= (int)$m['menu_id'] ?>" tabindex="0">
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
          <div class="p-3"><div class="alert alert-warning m-0">ไม่พบสินค้า</div></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ตะกร้า -->
    <div class="col-lg-3 mb-3">
      <div class="pos-card cart">
        <div class="d-flex align-items-center justify-content-between p-3 pt-3 pb-0">
          <div class="h5 mb-0 font-weight-bold">ออเดอร์</div>
          <a class="btn btn-sm btn-outline-light" href="front_store.php?action=clear" onclick="return confirm('ล้างออเดอร์ทั้งหมด?');">ล้าง</a>
        </div>
        <hr class="my-2" style="border-color:rgba(255,255,255,.25)">
        <div class="p-2 pt-0">
        <?php if(!empty($_SESSION['cart'])): ?>
          <form method="post" id="frmCart">
            <input type="hidden" name="action" value="update">
            <div class="table-responsive">
              <table class="table table-sm table-cart">
                <thead>
                  <tr>
                    <th>รายการ</th>
                    <th class="text-right">ราคา</th>
                    <th class="text-center" style="width:86px;">จำนวน</th>
                    <th class="text-right">รวม</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                <?php $sum=0.0; foreach($_SESSION['cart'] as $key=>$it):
                  $line=$it['price']*$it['qty']; $sum+=$line; ?>
                  <tr>
                    <td class="align-middle">
                      <div class="font-weight-bold" style="color:#0D4071"><?= htmlspecialchars($it['name'],ENT_QUOTES,'UTF-8') ?></div>
                      <?php if (!empty($it['note'])): ?>
                        <?php $parts = array_filter(array_map('trim', explode('|', $it['note']))); ?>
                        <div class="note-list">
                          <?php foreach ($parts as $p):
                            $k=''; $v=$p; if (strpos($p, ':')!==false) { [$k,$v] = array_map('trim', explode(':',$p,2)); } ?>
                            <span class="note-pill">
                              <?php if ($k!==''): ?><span class="k"><?= htmlspecialchars($k,ENT_QUOTES,'UTF-8') ?>:</span><?php endif; ?>
                              <span class="v"><?= htmlspecialchars($v,ENT_QUOTES,'UTF-8') ?></span>
                            </span>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td class="text-right align-middle"><?= money_fmt($it['price']) ?></td>
                    <td class="text-center align-middle">
                      <input class="form-control form-control-sm" type="number" name="qty[<?= safe_key($key) ?>]" value="<?= (int)$it['qty'] ?>" min="0">
                    </td>
                    <td class="text-right align-middle"><?= money_fmt($line) ?></td>
                    <td class="text-right align-middle">
                      <div class="btn-group btn-group-sm">
                        <!-- แก้: ใส่คลาส js-edit + data-key เพื่อเปิดในโมดัล -->
                        <a class="btn btn-outline-primary js-edit"
                           data-menu-id="<?= (int)$it['menu_id'] ?>"
                           data-key="<?= safe_key($key) ?>"
                           href="menu_detail.php?id=<?= (int)$it['menu_id'] ?>&edit=1&key=<?= urlencode($key) ?>"
                           title="แก้ไข">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <a class="btn btn-outline-danger" title="ลบ"
                           href="front_store.php?action=remove&key=<?= urlencode($key) ?>"
                           onclick="return confirm('ลบรายการนี้?');">
                          <i class="bi bi-trash"></i>
                        </a>
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
                <button class="btn btn-success btn-block" id="btnCheckout" style="font-weight:900; letter-spacing:.2px">สั่งออเดอร์ (F2)</button>
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

<!-- ===== Menu Detail Modal ===== -->
<div id="menuModal" class="psu-modal" aria-hidden="true">
  <div class="psu-modal__backdrop"></div>
  <div class="psu-modal__dialog">
    <button type="button" class="psu-modal__close" id="menuModalClose" aria-label="Close">&times;</button>
    <div class="psu-modal__body" id="menuModalBody">
      <div class="text-center py-5">กำลังโหลด…</div>
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
// ==== Voice helper (Web Speech Synthesis) ====
const voiceSwitch = document.getElementById('voiceSwitch');
const VOICE_FLAG_KEY = 'psu.voice.enabled';
try{ voiceSwitch.checked = localStorage.getItem(VOICE_FLAG_KEY) === '1'; }catch(_){}
voiceSwitch?.addEventListener('change', () => {
  try{ localStorage.setItem(VOICE_FLAG_KEY, voiceSwitch.checked ? '1':'0'); }catch(_){}
  if (voiceSwitch.checked) speakOnceWarmup();
});
function speak(text, lang='th-TH'){
  if (!('speechSynthesis' in window)) return false;
  if (!voiceSwitch?.checked) return false;
  const u = new SpeechSynthesisUtterance(text);
  u.lang = lang; u.rate = 1.0; u.pitch = 1.0;
  const pickThai = (window.speechSynthesis.getVoices() || []).find(v => /th(-|_|$)/i.test(v.lang));
  if (pickThai) u.voice = pickThai;
  window.speechSynthesis.cancel(); window.speechSynthesis.speak(u);
  return true;
}
function speakOnceWarmup(){
  try{
    const t = new SpeechSynthesisUtterance('พร้อมใช้งานเสียงแจ้งเตือนแล้ว');
    t.lang = 'th-TH'; window.speechSynthesis.cancel(); window.speechSynthesis.speak(t);
  }catch(_){}
}
</script>

<script>
// ==== Poll order status + Voice on READY ====
let lastSince = ''; let knownStatus = {};
function showToast(text, style='info'){
  const id = 't' + Date.now();
  const bg = style==='success' ? '#28a745' : (style==='danger' ? '#dc3545' : '#007bff');
  const el = document.createElement('div');
  el.id = id; el.style.cssText = `min-width:260px;margin-top:8px;background:${bg};color:#fff;padding:12px 14px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.2);font-weight:700`;
  el.textContent = text; document.getElementById('toast-zone').appendChild(el);
  setTimeout(()=> el.remove(), 4000);
}
async function poll(){
  try{
    const base = '../api/orders_feed.php?mine=1';
    const qs   = lastSince ? ('&since='+encodeURIComponent(lastSince)) : '';
    const r = await fetch(base+qs, {cache:'no-store'}); if(!r.ok) throw new Error('HTTP '+r.status);
    const data = await r.json(); if(!data.ok) return;
    if (!lastSince && data.now) lastSince = data.now;
    if(data.orders && data.orders.length){
      lastSince = data.orders[data.orders.length - 1].updated_at;
      for(const o of data.orders){
        const id = o.order_id, st = o.status;
        const prev = knownStatus[id]; knownStatus[id] = st;
        if (prev && prev !== st){
          if (st === 'ready'){
            const msg = `ออเดอร์ของคุณ หมายเลข ${id} เสร็จแล้ว!`;
            showToast(msg, 'success'); document.getElementById('ding')?.play().catch(()=>{}); speak(msg, 'th-TH');
          } else if (st === 'canceled'){
            showToast(`ออเดอร์ของคุณ #${id} ถูกยกเลิก`, 'danger');
          } else { showToast(`ออเดอร์ของคุณ #${id} → ${st}`); }
        } else if (!prev) { knownStatus[id] = st; }
      }
    }
  }catch(e){ }finally{ setTimeout(poll, 1500); }
}
window.addEventListener('load', () => {
  if ('speechSynthesis' in window) { window.speechSynthesis.onvoiceschanged = () => {}; }
  poll();
});
</script>

<script>
// ===== Modal logic =====
const modal = document.getElementById('menuModal');
const modalBody = document.getElementById('menuModalBody');
const closeBtn = document.getElementById('menuModalClose');
function openModal(){ modal.classList.add('is-open'); document.body.style.overflow='hidden'; }
function closeModal(){ modal.classList.remove('is-open'); document.body.style.overflow=''; }
closeBtn.onclick = closeModal;
document.querySelector('#menuModal .psu-modal__backdrop').addEventListener('click', closeModal);
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

/* ---------- เปิดโมดัลจากการ "คลิกสินค้า" เพื่อเพิ่มใหม่ ---------- */
document.addEventListener('click', async (e)=>{
  const card = e.target.closest('.product-mini');
  if(!card) return;
  e.preventDefault();

  const idFromData = card.dataset.id;
  const idFromHref = (()=>{ try{ return new URL(card.getAttribute('href'), location.href).searchParams.get('id'); }catch(_){ return null } })();
  const menuId = idFromData || idFromHref;
  if(!menuId) return;

  openModal();
  modalBody.innerHTML = '<div class="text-center py-5">กำลังโหลด…</div>';

  try{
    const r = await fetch('menu_detail.php?popup=1&id='+encodeURIComponent(menuId), {cache:'no-store', credentials:'same-origin'});
    const html = await r.text();
    modalBody.innerHTML = html;

    const form = modalBody.querySelector('#menuForm');
    if(form){
      const onSubmit = async (ev)=>{
        ev.preventDefault(); ev.stopPropagation();
        const fd = new FormData(form);
        fd.set('action','add');
        if(!fd.get('qty')) fd.set('qty','1');
        if(!fd.get('menu_id')) fd.set('menu_id', String(menuId));

        const pick = (name)=> (modalBody.querySelector(`input[name="${name}"]:checked`) || {}).value || '';
        const parts = [];
        const size = pick('size'), sweet = pick('sweet'), ice = pick('ice');
        if(size)  parts.push('ขนาด: '+size);
        if(sweet) parts.push('หวาน: '+sweet);
        if(ice)   parts.push('น้ำแข็ง: '+ice);
        const tops = Array.from(modalBody.querySelectorAll('input[name="toppings[]"]:checked')).map(x=>x.value);
        const free = (modalBody.querySelector('textarea[name="note_free"]')?.value || '').trim();
        if(tops.length) parts.push('ท็อปปิง: '+tops.join(', '));
        if(free) parts.push('หมายเหตุ: '+free);
        fd.set('note', parts.join(' | '));

        try{
          const res = await fetch('front_store.php', {
            method:'POST', body:fd, credentials:'same-origin', cache:'no-store',
            headers:{ 'X-Requested-With': 'XMLHttpRequest' }
          });
          try{ await res.json(); }catch(_){}
          closeModal();
          const url = new URL(window.location.href); url.searchParams.set('t', Date.now().toString());
          window.location.assign(url.toString());
        }catch(err){ alert('เพิ่มลงตะกร้าไม่สำเร็จ'); }
      };
      form.addEventListener('submit', onSubmit, { once:true });
    }
  }catch(err){
    modalBody.innerHTML = '<div class="p-4 text-danger">โหลดไม่สำเร็จ</div>';
  }
});

/* ---------- ใหม่: เปิดโมดัลจากปุ่ม "แก้ไข" ในตะกร้า ---------- */
document.addEventListener('click', async (e)=>{
  const editBtn = e.target.closest('.js-edit');
  if(!editBtn) return;
  e.preventDefault();

  const menuId = editBtn.getAttribute('data-menu-id');
  const oldKey = editBtn.getAttribute('data-key');
  if(!menuId || !oldKey) return;

  openModal();
  modalBody.innerHTML = '<div class="text-center py-5">กำลังโหลด…</div>';

  try{
    // โหลดฟอร์มแก้ไข
    const url = 'menu_detail.php?popup=1&id=' + encodeURIComponent(menuId) + '&edit=1&key=' + encodeURIComponent(oldKey);
    const r = await fetch(url, {cache:'no-store', credentials:'same-origin'});
    const html = await r.text();
    modalBody.innerHTML = html;

    const form = modalBody.querySelector('#menuForm');
    if(form){
      const onSubmit = async (ev)=>{
        ev.preventDefault(); ev.stopPropagation();

        const fd = new FormData(form);
        // บังคับโหมดแก้ไข
        fd.set('action','add');      // ใช้เส้นทางเดียวกับ add ใน PHP
        fd.set('edit','1');          // บอกว่าเป็น edit
        fd.set('old_key', oldKey);   // key เดิมในตะกร้า
        if(!fd.get('qty')) fd.set('qty','1');
        if(!fd.get('menu_id')) fd.set('menu_id', String(menuId));

        // รวบรวมโน้ตจากตัวเลือก
        const pick = (name)=> (modalBody.querySelector(`input[name="${name}"]:checked`) || {}).value || '';
        const parts = [];
        const size = pick('size'), sweet = pick('sweet'), ice = pick('ice');
        if(size)  parts.push('ขนาด: '+size);
        if(sweet) parts.push('หวาน: '+sweet);
        if(ice)   parts.push('น้ำแข็ง: '+ice);
        const tops = Array.from(modalBody.querySelectorAll('input[name="toppings[]"]:checked')).map(x=>x.value);
        const free = (modalBody.querySelector('textarea[name="note_free"]')?.value || '').trim();
        if(tops.length) parts.push('ท็อปปิง: '+tops.join(', '));
        if(free) parts.push('หมายเหตุ: '+free);
        fd.set('note', parts.join(' | '));

        try{
          const res = await fetch('front_store.php', {
            method:'POST', body:fd, credentials:'same-origin', cache:'no-store',
            headers:{ 'X-Requested-With': 'XMLHttpRequest' }
          });
          try{ await res.json(); }catch(_){}
          closeModal();
          // รีเฟรชเพื่อให้ตะกร้าโชว์ค่าที่แก้ไขล่าสุดเสมอ
          const url = new URL(window.location.href); url.searchParams.set('t', Date.now().toString());
          window.location.assign(url.toString());
        }catch(err){ alert('แก้ไขรายการไม่สำเร็จ'); }
      };
      form.addEventListener('submit', onSubmit, { once:true });
    }
  }catch(err){
    modalBody.innerHTML = '<div class="p-4 text-danger">โหลดไม่สำเร็จ</div>';
  }
});
</script>

</body>
</html>

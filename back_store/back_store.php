<?php
// back_store/back_store.php — แสดงออเดอร์ + ตัวช่วยค้นหาเมนู/วันเวลา + PSU Topbar + ดูสลิป (Full fixed) + ดูสูตรเมนู
// (ตกแต่ง UI: การ์ดคอนทราสต์ชัด, แถบสีซ้าย, ปุ่ม/ชิปอ่านง่าย)

declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$allow_roles = ['admin','employee','kitchen','back','barista'];
if (!empty($_SESSION['role']) && !in_array($_SESSION['role'], $allow_roles, true)) {
  header("Location: ../index.php"); exit;
}

/* ---------- Endpoint: slips ---------- */
if (($_GET['action'] ?? '') === 'slips') {
  header('Content-Type: application/json; charset=utf-8');
  $oid = (int)($_GET['order_id'] ?? 0);
  if ($oid <= 0) { echo json_encode(['ok'=>false,'msg'=>'order_id ไม่ถูกต้อง']); exit; }
  $rows = [];
  if ($st = $conn->prepare("SELECT id, order_id, user_id, file_path, mime, size_bytes, uploaded_at, note
                            FROM payment_slips
                            WHERE order_id=? ORDER BY id DESC")) {
    $st->bind_param("i", $oid); $st->execute();
    $rs = $st->get_result();
    while($r=$rs->fetch_assoc()) $rows[] = $r;
    $st->close();
  }
  echo json_encode(['ok'=>true,'order_id'=>$oid,'slips'=>$rows]); exit;
}

/* ---------- Endpoint: recipe ---------- */
if (($_GET['action'] ?? '') === 'recipe') {
  header('Content-Type: application/json; charset=utf-8');
  $mid = (int)($_GET['menu_id'] ?? 0);
  if ($mid <= 0) { echo json_encode(['ok'=>false,'msg'=>'menu_id ไม่ถูกต้อง']); exit; }

  $menu = null; $rid = 0;
  if ($st = $conn->prepare("
      SELECT r.recipe_id, r.menu_id, COALESCE(r.title, m.name) AS title, m.name AS menu_name
      FROM recipe_headers r
      JOIN menu m ON m.menu_id=r.menu_id
      WHERE r.menu_id=? ORDER BY r.recipe_id DESC LIMIT 1
  ")) {
    $st->bind_param("i",$mid); $st->execute();
    $rs=$st->get_result(); $menu=$rs->fetch_assoc(); $st->close();
    if ($menu) $rid = (int)$menu['recipe_id'];
  }
  if ($rid===0) { echo json_encode(['ok'=>false,'msg'=>'ยังไม่มีสูตรของเมนูนี้']); exit; }

  $steps = [];
  if ($st = $conn->prepare("
      SELECT step_id, step_no, step_text, sort_order
      FROM recipe_steps
      WHERE recipe_id=?
      ORDER BY (CASE WHEN sort_order>0 THEN sort_order ELSE step_no END), step_no
  ")) {
    $st->bind_param("i",$rid); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()){ $r['ingredients']=[]; $steps[(int)$r['step_id']]=$r; }
    $st->close();
  }

  $common = [];
  if ($st = $conn->prepare("
      SELECT ingredient_id, step_id, name, qty, unit, note, sort_order
      FROM recipe_ingredients
      WHERE recipe_id=?
      ORDER BY (CASE WHEN sort_order>0 THEN sort_order ELSE ingredient_id END)
  ")) {
    $st->bind_param("i",$rid); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()){
      $sid = (int)($r['step_id'] ?? 0);
      if ($sid===0) $common[]=$r; else if(isset($steps[$sid])) $steps[$sid]['ingredients'][]=$r;
    }
    $st->close();
  }

  $stepsArr = array_values($steps);
  usort($stepsArr,function($a,$b){
    $aa = $a['sort_order'] ?: $a['step_no']; $bb = $b['sort_order'] ?: $b['step_no']; return $aa <=> $bb;
  });

  echo json_encode([
    'ok'=>true,
    'menu'=>['menu_id'=>$menu['menu_id'], 'title'=>$menu['title'], 'name'=>$menu['menu_name']],
    'common'=>$common, 'steps'=>$stepsArr
  ]); exit;
}

/* ---------- POST: update status ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
  $oid = (int)$_POST['order_id'];
  $to  = $_POST['action'] === 'ready' ? 'ready' : ($_POST['action']==='canceled'?'canceled':'');
  $ok=false;
  if ($oid>0 && $to!=='') {
    $stmt=$conn->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE order_id=? AND status='pending'");
    $stmt->bind_param("si",$to,$oid); $stmt->execute(); $ok = ($stmt->affected_rows>0); $stmt->close();
  }
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) { header('Content-Type: application/json'); echo json_encode(['ok'=>$ok]); exit; }
  header("Location: back_store.php"); exit;
}

/* ---------- Filters ---------- */
$status     = $_GET['status']     ?? 'pending';
$q          = trim((string)($_GET['q'] ?? ''));
$date_from  = trim((string)($_GET['date_from'] ?? ''));
$time_from  = trim((string)($_GET['time_from'] ?? ''));
$date_to    = trim((string)($_GET['date_to'] ?? ''));
$time_to    = trim((string)($_GET['time_to'] ?? ''));

$dt_from = $date_from ? ($date_from.' '.($time_from?:'00:00:00')) : '';
$dt_to   = $date_to   ? ($date_to  .' '.($time_to  ?: '23:59:59')) : '';

/* ---------- Query orders ---------- */
$orders=[]; $where="1=1"; $types=''; $params=[];
if ($status!=='all'){ $where.=" AND o.status=?"; $types.='s'; $params[]=$status; }
if ($dt_from!==''){  $where.=" AND o.order_time>=?"; $types.='s'; $params[]=$dt_from; }
if ($dt_to!==''){    $where.=" AND o.order_time<=?"; $types.='s'; $params[]=$dt_to; }
if ($q!==''){
  $where.=" AND EXISTS(SELECT 1 FROM order_details d JOIN menu m ON m.menu_id=d.menu_id WHERE d.order_id=o.order_id AND m.name LIKE ?)";
  $types.='s'; $params[]='%'.$q.'%';
}
$sql="
  SELECT o.order_id,o.user_id,o.order_time,o.status,o.total_price,
         u.username,u.name, COALESCE(ps.cnt,0) AS slip_count
  FROM orders o
  LEFT JOIN users u ON u.user_id=o.user_id
  LEFT JOIN (SELECT order_id,COUNT(*) AS cnt FROM payment_slips GROUP BY order_id) ps ON ps.order_id=o.order_id
  WHERE $where
  ORDER BY o.order_id DESC";
$stmt=$conn->prepare($sql);
if ($types!=='') $stmt->bind_param($types, ...$params);
$stmt->execute(); $res=$stmt->get_result();
while($row=$res->fetch_assoc()) $orders[]=$row;
$stmt->close();

/* ---------- Lines ---------- */
function get_order_lines(mysqli $conn, int $oid): array{
  $rows=[]; $stmt=$conn->prepare("
    SELECT d.order_detail_id,d.menu_id,d.quantity,d.note,d.total_price,m.name AS menu_name
    FROM order_details d JOIN menu m ON m.menu_id=d.menu_id
    WHERE d.order_id=? ORDER BY d.order_detail_id");
  $stmt->bind_param("i",$oid); $stmt->execute(); $res=$stmt->get_result();
  while($r=$res->fetch_assoc()) $rows[]=$r; $stmt->close(); return $rows;
}
function money_fmt($n){ return number_format((float)$n,2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>หลังร้าน • ค้นหาออเดอร์/ค้างทำ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
/* ===== Theme (Teal-Graphite, คอนทราสต์สูง) ===== */
:root{
  --bg1:#222831; --bg2:#393E46;
  --surface:#1C2228; --surface2:#232A31; --surface3:#2B323A;
  --ink:#F4F7F8; --ink-dim:#CFEAED; --muted:#B9C2C9;

  --accent:#00ADB5; --accent-2:#27C8CF; --accent-3:#73E2E6;
  --ok:#2ecc71; --bad:#e74c3c;

  --cardLight:#0f1820;           /* ใช้กับหัวการ์ด */
  --cardBg:#0B0F14;              /* ไล่พื้นหลังการ์ด */
  --cardEdge:#0ad3db;            /* แถบซ้ายการ์ด */
  --cardBorder:rgba(255,255,255,.12);

  --shadow:0 14px 34px rgba(0,0,0,.46);
  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
}

/* ===== Page ===== */
body{
  background:linear-gradient(135deg,var(--bg1),var(--bg2));
  color:var(--ink); font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{max-width:1400px;margin:26px auto;padding:0 16px;}
.brand{font-weight:900; letter-spacing:.3px}

/* ===== Topbar ===== */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin:16px auto 12px; border-radius:14px;
  background:color-mix(in oklab, var(--surface), white 6%);
  border:1px solid rgba(255,255,255,.18); box-shadow:0 10px 26px rgba(0,0,0,.38); max-width:1400px;
}
.topbar-actions{ gap:8px }
.badge-user{ background:color-mix(in oklab, var(--surface2), white 6%); color:var(--ink); font-weight:800; border-radius:999px; border:1px solid rgba(255,255,255,.22) }
a.btn.btn-primary.btn-sm{ font-weight:800 }

/* ===== Filter ===== */
.filter{
  background:color-mix(in oklab, var(--surface2), white 6%);
  border:1px solid rgba(255,255,255,.18); border-radius:14px; padding:12px; box-shadow:0 10px 22px rgba(0,0,0,.32);
}
.filter label{font-weight:800; font-size:.9rem}
.filter .form-control, .filter .custom-select{
  background:var(--surface3); color:var(--ink); border-radius:999px; border:1.5px solid rgba(255,255,255,.22);
}
.filter .form-control::placeholder{color:#9fb0ba}
.filter .form-control:focus{ box-shadow:0 0 0 .18rem rgba(0,173,181,.35) }
.filter .btn-find,.filter .btn-clear{font-weight:900; border-radius:999px}

/* ===== Cards grid ===== */
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(540px,1fr));gap:18px}

/* การ์ดที่ “ตัดพื้นหลัง” ชัด: ใช้แถบสีซ้าย + กรอบเรือง */
.card{
  position:relative; overflow:hidden;
  background:
    linear-gradient(180deg, color-mix(in oklab, var(--cardBg), black 8%), color-mix(in oklab, var(--surface2), white 4%));
  color:#e9f2f5; border:1px solid var(--cardBorder); border-radius:18px; box-shadow:var(--shadow);
  transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.card::before{ /* แถบสีตัดพื้นหลัง */
  content:""; position:absolute; inset:0 0 0 auto; width:6px; background:linear-gradient(180deg,var(--cardEdge),#1ea5ab);
  left:0; right:auto; border-radius:18px 0 0 18px; opacity:.95;
}
.card:hover{ transform:translateY(-3px); box-shadow:var(--shadow-lg); border-color:rgba(255,255,255,.24) }

/* head */
.head{
  display:flex;justify-content:space-between;align-items:flex-start;
  background:color-mix(in oklab, var(--cardLight), black 2%); border-bottom:1px solid rgba(255,255,255,.12);
  padding:12px 16px
}
.oid{font-weight:900;color:#bff6f8}
.meta{color:#a9bcc7; font-weight:700}

/* lines */
.line{display:flex;justify-content:space-between;padding:10px 16px;border-bottom:1px dashed rgba(255,255,255,.14)}
.line:last-child{border-bottom:none}
.item{font-weight:900;color:#e9fcff}
.note{
  margin-top:6px;font-size:.9rem;background:rgba(0,173,181,.08);color:#bfeef1;
  border:1px dashed rgba(0,173,181,.35);border-radius:10px;padding:6px 8px
}
.qty{min-width:48px;text-align:center;font-weight:900;background:var(--accent);color:#062b2f;border-radius:999px;padding:4px 10px}

/* summary */
.summary{
  display:flex;justify-content:space-between;padding:12px 16px 14px;font-weight:900;color:#cfeaed;
  background:color-mix(in oklab, var(--surface3), white 4%); border-top:1px solid rgba(255,255,255,.12)
}

/* actions */
.actions{display:flex;gap:10px;background:color-mix(in oklab, var(--surface3), white 4%);border-top:1px solid rgba(255,255,255,.12);padding:12px}
.btn-ready{flex:1;background:linear-gradient(180deg,#2ecc71,#22b862);color:#042913;font-weight:900;border-radius:12px;padding:10px;border:0}
.btn-cancel{flex:1;background:linear-gradient(180deg,#ff6b6b,#e74c3c);color:#2a0202;font-weight:900;border-radius:12px;padding:10px;border:0}
.btn-ready:hover,.btn-cancel:hover{filter:brightness(1.05)}
.btn-slips{background:linear-gradient(180deg,#00ADB5,#27C8CF);color:#042e31;border:none;border-radius:10px;padding:6px 10px;font-weight:900}
.btn-slips[disabled]{opacity:.45; cursor:not-allowed; background:color-mix(in oklab, var(--surface3), white 8%); color:#8da5ad}

/* pay chip */
.pay-chip{ display:inline-flex; align-items:center; gap:6px; border-radius:999px; font-weight:900; padding:4px 10px;
  border:1px solid rgba(255,255,255,.22); background:rgba(0,173,181,.12); color:#bff6f8; margin-top:6px; }
.pay-chip.cash{ background:rgba(255,196,0,.12); border-color:rgba(255,196,0,.25); color:#ffe5a4; }

/* empty */
.empty{background:color-mix(in oklab, var(--surface2), white 8%);border:1px dashed rgba(255,255,255,.22);border-radius:12px;padding:24px;text-align:center}

/* Modals / Lightbox (ปรับสีให้เข้าธีม) */
.psu-modal{ position:fixed; inset:0; display:none; z-index:3000; }
.psu-modal.is-open{ display:block; }
.psu-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.6); backdrop-filter: blur(6px); }
.psu-modal__dialog{ position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(900px,96vw); max-height:92vh; overflow:auto; background:#0e141a; color:#e9f2f5; border:1px solid rgba(255,255,255,.12); border-radius:18px; box-shadow:var(--shadow-lg); }
.psu-modal__close{ position:absolute; right:12px; top:8px; border:0; background:transparent; font-size:32px; font-weight:900; line-height:1; cursor:pointer; color:#9cdde0; }
.slip-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
.slip-card{ border:1px solid rgba(255,255,255,.12); border-radius:12px; overflow:hidden; background:#0c1016; transition:transform .12s }
.slip-card:hover{ transform:translateY(-2px) }
.slip-card img{ width:100%; height:220px; object-fit:cover; display:block; background:#0b0f14; user-select:none }
.slip-meta{ padding:8px; color:#a9bcc7; font-size:.9rem }

/* Lightbox */
.lb{ position:fixed; inset:0; z-index:4000; display:none; }
.lb.is-open{ display:block; }
.lb__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.9); }
.lb__stage{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.lb__imgwrap{ position:relative; touch-action:none; cursor:grab; }
.lb__imgwrap:active{ cursor:grabbing; }
.lb__img{ max-width:none; max-height:none; will-change:transform; user-select:none; pointer-events:none; }
.lb__ui{ position:absolute; left:0; right:0; top:0; display:flex; justify-content:space-between; padding:8px 10px; color:#e9f2f5; }
.lb__title{ font-weight:800; padding:6px 10px; background:rgba(0,0,0,.35); border-radius:10px; }
.lb__buttons{ display:flex; gap:8px; }
.lb__btn{ background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); color:#e9f2f5; border-radius:10px; padding:6px 10px; font-weight:800; }
.lb__btn:hover{ background:rgba(255,255,255,.18); }
.lb__nav{ position:absolute; top:50%; transform:translateY(-50%); display:flex; align-items:center; gap:8px; }
.lb__prev{ left:10px; } .lb__next{ right:10px; }
.lb__navbtn{ background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); color:#e9f2f5; border-radius:12px; padding:10px 14px; font-weight:900; font-size:18px; }

/* ✅ Recipe Modal tone */
#recipeModal .h5,#recipeModal #rcpTitle{ color:#bff6f8; }
#recipeModal #rcpBody{ color:#dbf7f9; }
.rcp-section{ margin-bottom:14px; }
.rcp-h{ font-weight:900; color:#9be9ee; margin-bottom:6px; }
.rcp-steps{ counter-reset: stepnum; }
.rcp-step{ margin:10px 0; }
.rcp-step .rcp-stephead{ font-weight:900; color:#d6fbff; }
.rcp-ing{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size:.95rem; }
.rcp-ing-row{ display:grid; grid-template-columns: 1fr 80px 80px 1fr; gap:10px; padding:4px 0; border-bottom:1px dashed rgba(255,255,255,.14); }
.rcp-ing-row:last-child{ border-bottom:none; }
.rcp-ing-head{ font-weight:800; color:#9be9ee; }

@media (max-width:576px){
  .topbar{flex-wrap:wrap; gap:8px}
  .topbar-actions{width:100%; justify-content:flex-end}
  .lb__btn{ padding:6px 8px } .lb__navbtn{ padding:8px 12px }
}
/* ปุ่ม "ดูสูตร..." โทนส้ม */
.btn-recipe{
  --rc:#FF8A00;                 /* ส้มหลัก */
  --rc-ink:#2b1700;             /* สีตัวอักษรตอน hover */
  color:var(--rc);
  border:1.5px solid var(--rc);
  background:transparent;
  font-weight:900;
  border-radius:10px;
}
.btn-recipe:hover,
.btn-recipe:active{
  background:var(--rc);
  color:var(--rc-ink);
  border-color:var(--rc);
  filter:brightness(1.02);
}
.btn-recipe:focus-visible{
  outline:3px solid color-mix(in oklab, var(--rc) 55%, white);
  outline-offset:2px;
}

/* กัน Bootstrap กลับไปเป็นฟ้า */
.btn-recipe.btn-outline-primary{
  color:var(--rc) !important;
  border-color:var(--rc) !important;
}
.btn-recipe.btn-outline-primary:hover{
  color:var(--rc-ink) !important;
  background:var(--rc) !important;
  border-color:var(--rc) !important;
}

</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar d-flex align-items-center justify-content-between">
  <div class="d-flex align-items-center">
    <h4 class="m-0 brand">PSU Blue Cafe • หลังร้าน</h4>
  </div>
  <div class="d-flex align-items-center topbar-actions">
    <a href="back_store_history.php" class="btn btn-primary btn-sm mr-2">ประวัติออเดอร์</a>
    <a href="../SelectRole/role.php" class="btn btn-primary btn-sm mr-2">ตําเเหน่ง</a>
    <span class="badge badge-user px-3 py-2 mr-2">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
    <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
  </div>
</div>

<div class="wrap">
  <!-- Filter -->
  <form class="filter mb-3" method="get">
    <div class="form-row">
      <div class="col-md-2 mb-2">
        <label>สถานะ</label>
        <select name="status" class="custom-select">
          <?php
            $opts=['pending'=>'Pending','ready'=>'Ready','canceled'=>'Canceled','all'=>'(ทั้งหมด)'];
            foreach($opts as $k=>$v){ $sel=($status===$k)?'selected':''; echo '<option value="'.htmlspecialchars($k,ENT_QUOTES).'" '.$sel.'>'.$v.'</option>'; }
          ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label>ค้นหาชื่อเมนู</label>
        <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($q,ENT_QUOTES,'UTF-8') ?>" placeholder="พิมพ์ชื่อเมนู เช่น ชาไทย">
      </div>
      <div class="col-md-3 mb-2">
        <label>ตั้งแต่ (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from,ENT_QUOTES,'UTF-8') ?>"></div>
          <div class="col"><input type="time" name="time_from" class="form-control" value="<?= htmlspecialchars($time_from,ENT_QUOTES,'UTF-8') ?>"></div>
        </div>
      </div>
      <div class="col-md-3 mb-2">
        <label>ถึง (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to,ENT_QUOTES,'UTF-8') ?>"></div>
          <div class="col"><input type="time" name="time_to" class="form-control" value="<?= htmlspecialchars($time_to,ENT_QUOTES,'UTF-8') ?>"></div>
        </div>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <button class="btn btn-primary btn-block btn-find">ค้นหา</button>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <a href="back_store.php" class="btn btn-light btn-block btn-clear">ล้าง</a>
      </div>
    </div>
  </form>

  <div class="grid" id="grid">
    <?php if (empty($orders)): ?>
      <div class="empty">ไม่มีออเดอร์ตามเงื่อนไข</div>
    <?php else: ?>
      <?php foreach ($orders as $o): $lines = get_order_lines($conn,(int)$o['order_id']); ?>
        <?php $isTransfer=((int)$o['slip_count']>0); $pay_text=$isTransfer?'💳 โอนเงิน':'💵 เงินสด'; $pay_class=$isTransfer?'':' cash'; ?>
        <div class="card" data-order-id="<?= (int)$o['order_id'] ?>" tabindex="0" aria-label="ออเดอร์ #<?= (int)$o['order_id'] ?>">
          <div class="head">
            <div>
              <div class="oid">#<?= (int)$o['order_id'] ?> — <?= htmlspecialchars($o['username'] ?? 'user',ENT_QUOTES,'UTF-8') ?></div>
              <div class="meta">
                <?= htmlspecialchars($o['order_time'],ENT_QUOTES,'UTF-8') ?>
                · สถานะ: <?= htmlspecialchars($o['status'],ENT_QUOTES,'UTF-8') ?>
                <div class="pay-chip<?= $pay_class ?>"><?= $pay_text ?></div>
              </div>
            </div>
            <div class="text-right">
              <?php if($o['status']==='pending'): ?>
                <span class="badge badge-warning p-2 font-weight-bold d-block mb-1">pending</span>
              <?php elseif($o['status']==='ready'): ?>
                <span class="badge badge-success p-2 font-weight-bold d-block mb-1">ready</span>
              <?php else: ?>
                <span class="badge badge-danger p-2 font-weight-bold d-block mb-1">canceled</span>
              <?php endif; ?>
              <button class="btn-slips btn btn-sm mt-1" data-oid="<?= (int)$o['order_id'] ?>" <?= ((int)$o['slip_count']>0 ? '' : 'disabled') ?>>
                ดูสลิป (<?= (int)$o['slip_count'] ?>)
              </button>
            </div>
          </div>

          <?php foreach ($lines as $ln): ?>
          <div class="line">
            <div style="padding-right:12px;max-width:78%">
              <div class="item">
                <?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?>
                <button type="button" class="btn btn-sm btn-outline-primary ml-2 btn-recipe"
                        data-menu-id="<?= (int)$ln['menu_id'] ?>"
                        title="ดูสูตร<?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?>"
                        aria-label="ดูสูตร<?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?>">ดูสูตร<?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?></button>
              </div>
              <?php if(!empty($ln['note'])): ?>
                <div class="note">📝 <?= htmlspecialchars($ln['note'],ENT_QUOTES,'UTF-8') ?></div>
              <?php endif; ?>
            </div>
            <div><span class="qty">x <?= (int)$ln['quantity'] ?></span></div>
          </div>
          <?php endforeach; ?>

          <div class="summary">
            <div>ยอดรวมออเดอร์</div>
            <div><?= money_fmt($o['total_price']) ?> ฿</div>
          </div>

          <?php if($o['status']==='pending'): ?>
          <div class="actions">
            <form class="m-0 js-status" method="post">
              <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
              <input type="hidden" name="action" value="ready">
              <button class="btn btn-ready btn-block" type="submit">เสร็จแล้ว</button>
            </form>
            <form class="m-0 js-status" method="post">
              <input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>">
              <input type="hidden" name="action" value="canceled">
              <button class="btn btn-cancel btn-block" type="submit">ยกเลิก</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Slip Modal -->
<div id="slipModal" class="psu-modal" aria-hidden="true">
  <div class="psu-modal__backdrop"></div>
  <div class="psu-modal__dialog">
    <button type="button" class="psu-modal__close" id="slipClose" aria-label="Close">&times;</button>
    <div class="p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="h5 mb-0">สลิปการชำระเงิน <span id="slipTitleOid"></span></div>
        <span class="badge badge-primary" id="slipCountBadge"></span>
      </div>
      <div id="slipZone" class="slip-grid"></div>
      <div id="slipMsg" class="mt-3"></div>
    </div>
  </div>
</div>

<!-- Recipe Modal -->
<div id="recipeModal" class="psu-modal" aria-hidden="true">
  <div class="psu-modal__backdrop"></div>
  <div class="psu-modal__dialog">
    <button type="button" class="psu-modal__close" id="recipeClose" aria-label="Close">&times;</button>
    <div class="p-3 p-md-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="h5 mb-0">สูตรเมนู <span id="rcpTitle"></span></div>
      </div>
      <div id="rcpBody"></div>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div id="lightbox" class="lb" aria-hidden="true">
  <div class="lb__backdrop"></div>
  <div class="lb__stage"><div class="lb__imgwrap"><img id="lbImg" class="lb__img" alt=""></div></div>
  <div class="lb__ui">
    <div class="lb__title" id="lbTitle">ภาพ 1/1</div>
    <div class="lb__buttons">
      <a id="lbDownload" class="lb__btn" download>ดาวน์โหลด</a>
      <button id="lbFit" class="lb__btn" type="button">พอดีจอ</button>
      <button id="lbZoomIn" class="lb__btn" type="button">ซูม +</button>
      <button id="lbZoomOut" class="lb__btn" type="button">ซูม −</button>
      <button id="lbClose" class="lb__btn" type="button">ปิด (Esc)</button>
    </div>
  </div>
  <div class="lb__nav lb__prev"><button id="lbPrev" class="lb__navbtn" type="button">‹</button></div>
  <div class="lb__nav lb__next"><button id="lbNext" class="lb__navbtn" type="button">›</button></div>
</div>

<script>
/** Poll เฉพาะกรณี: ดู pending แบบไม่ใส่ตัวกรอง **/
const url=new URL(location.href);
const status=url.searchParams.get('status')||'pending';
const hasFilters=(url.searchParams.get('q')||'').trim()!==''||(url.searchParams.get('date_from')||'')!==''||(url.searchParams.get('date_to')||'')!==''||(url.searchParams.get('time_from')||'')!==''||(url.searchParams.get('time_to')||'')!==''||(status!=='pending'&&status!==null);
const FEED_URL='../api/orders_feed.php';
const GET_URL=(id)=>'../api/order_get.php?id='+encodeURIComponent(id);
let lastSince=''; const knownStatus={};
function escapeHtml(s){return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function setPayChip(el,isTransfer){let chip=el.querySelector('.pay-chip'); if(!chip){chip=document.createElement('div'); chip.className='pay-chip'; el.appendChild(chip);} chip.classList.toggle('cash',!isTransfer); chip.textContent=(isTransfer?'💳 โอนเงิน':'💵 เงินสด');}
function renderCard(order,lines){
  const grid=document.getElementById('grid'); const id=order.order_id;
  let card=document.querySelector(`[data-order-id="${id}"]`); if(card) card.remove();
  const div=document.createElement('div'); div.className='card'; div.setAttribute('data-order-id',id); div.tabIndex=0; div.setAttribute('aria-label','ออเดอร์ #'+id);
  const slipCount=Number(order.slip_count||0), isTransfer=slipCount>0;
  div.innerHTML=`
    <div class="head">
      <div>
        <div class="oid">#${id} — ${escapeHtml(order.username||'user')}</div>
        <div class="meta">
          ${escapeHtml(order.order_time)} · สถานะ: ${escapeHtml(order.status)}
          <div class="pay-chip${isTransfer?'':' cash'}">${isTransfer?'💳 โอนเงิน':'💵 เงินสด'}</div>
        </div>
      </div>
      <div class="text-right">
        <span class="badge ${order.status==='pending'?'badge-warning':(order.status==='ready'?'badge-success':'badge-danger')} p-2 font-weight-bold d-block mb-1">${escapeHtml(order.status)}</span>
        <button class="btn-slips btn btn-sm mt-1" data-oid="${id}" ${slipCount>0?'':'disabled'}>ดูสลิป (${slipCount})</button>
      </div>
    </div>
    ${lines.map(ln=>`
      <div class="line">
        <div style="padding-right:12px;max-width:78%">
          <div class="item">
            ${escapeHtml(ln.menu_name)}
            <button type="button" class="btn btn-sm btn-outline-primary ml-2 btn-recipe"
              data-menu-id="${parseInt(ln.menu_id,10)||0}" title="ดูสูตร${escapeHtml(ln.menu_name)}"
              aria-label="ดูสูตร${escapeHtml(ln.menu_name)}">ดูสูตร${escapeHtml(ln.menu_name)}</button>
          </div>
          ${ln.note?`<div class="note">📝 ${escapeHtml(ln.note)}</div>`:``}
        </div>
        <div><span class="qty">x ${parseInt(ln.quantity,10)||0}</span></div>
      </div>
    `).join('')}
    <div class="summary"><div>ยอดรวมออเดอร์</div><div>${Number(order.total_price).toFixed(2)} ฿</div></div>
    ${order.status==='pending'?`
      <div class="actions">
        <form class="m-0 js-status" method="post">
          <input type="hidden" name="order_id" value="${id}">
          <input type="hidden" name="action" value="ready">
          <button class="btn btn-ready btn-block" type="submit">เสร็จแล้ว</button>
        </form>
        <form class="m-0 js-status" method="post">
          <input type="hidden" name="order_id" value="${id}">
          <input type="hidden" name="action" value="canceled">
          <button class="btn btn-cancel btn-block" type="submit">ยกเลิก</button>
        </form>
      </div>`:``}
  `;
  grid.prepend(div);
}
function hideCard(id){ const card=document.querySelector(`[data-order-id="${id}"]`); if(!card) return; card.style.transition='opacity .2s ease, transform .2s ease'; card.style.opacity='0'; card.style.transform='translateY(-6px)'; setTimeout(()=>card.remove(),200); }
async function poll(){
  try{
    const qs=lastSince?('?since='+encodeURIComponent(lastSince)):'';
    const r=await fetch(FEED_URL+qs,{cache:'no-store'}); if(!r.ok) throw new Error('HTTP '+r.status);
    const data=await r.json(); if(data.now) lastSince=data.now;

    if(Array.isArray(data.orders)&&data.orders.length){
      for(const o of data.orders){
        const id=o.order_id, st=o.status, prev=knownStatus[id]; knownStatus[id]=st;
        const card=document.querySelector(`[data-order-id="${id}"]`);
        if(st==='pending'){
          if(card){
            const badge=card.querySelector('.badge'); if(badge) badge.textContent='pending';
            const btnSlip=card.querySelector('.btn-slips'); if(btnSlip){ const newN=Number(o.slip_count||0); btnSlip.textContent=`ดูสลิป (${newN})`; (newN>0)?btnSlip.removeAttribute('disabled'):btnSlip.setAttribute('disabled','disabled'); }
            const meta=card.querySelector('.meta'); if(meta) setPayChip(meta, Number(o.slip_count||0)>0);
          }else{
            try{ const rr=await fetch(GET_URL(id),{cache:'no-store'}); const j=await rr.json(); if(j&&j.ok) renderCard(j.order, j.lines||[]);}catch(e){}
          }
        }else{ if(card) hideCard(id); }
      }
    }
    if(Array.isArray(data.slips)&&data.slips.length){
      for(const s of data.slips){
        const card=document.querySelector(`[data-order-id="${s.order_id}"]`); if(!card) continue;
        const btn=card.querySelector('.btn-slips'); if(btn){ const m=btn.textContent.match(/\((\d+)\)/); let n=m?parseInt(m[1],10):0; n++; btn.textContent=`ดูสลิป (${n})`; btn.removeAttribute('disabled'); }
        const meta=card.querySelector('.meta'); if(meta) setPayChip(meta,true);
      }
    }
  }catch(e){ }finally{ setTimeout(poll,1500); }
}
document.addEventListener('submit', async (e)=>{
  const form=e.target.closest('form.js-status'); if(!form) return; e.preventDefault();
  const card=form.closest('[data-order-id]'); const btn=form.querySelector('button[type="submit"]'); if(btn) btn.disabled=true;
  try{ const res=await fetch(location.href,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}}); const data=await res.json();
    if(data&&data.ok){ hideCard(card.getAttribute('data-order-id')); } else { alert('อัปเดตไม่สำเร็จ'); if(btn) btn.disabled=false; }
  }catch(err){ alert('เชื่อมต่อไม่สำเร็จ'); if(btn) btn.disabled=false; }
});
if(!hasFilters && status==='pending'){ window.addEventListener('load', poll); }

/* ===== Slips Modal ===== */
const slipModal=document.getElementById('slipModal'), slipClose=document.getElementById('slipClose'), slipZone=document.getElementById('slipZone'), slipMsg=document.getElementById('slipMsg'), slipTitleOid=document.getElementById('slipTitleOid'), slipCountBadge=document.getElementById('slipCountBadge');
function openSlipModal(){ slipModal.classList.add('is-open'); document.body.style.overflow='hidden'; }
function closeSlipModal(){ slipModal.classList.remove('is-open'); document.body.style.overflow=''; }
slipClose.addEventListener('click', closeSlipModal);
document.querySelector('#slipModal .psu-modal__backdrop').addEventListener('click', closeSlipModal);
document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closeSlipModal(); });

async function loadSlips(oid){
  slipZone.innerHTML=''; slipMsg.textContent='กำลังโหลด...'; slipTitleOid.textContent='#'+oid; slipCountBadge.textContent='';
  openSlipModal();
  try{
    const r=await fetch(`back_store.php?action=slips&order_id=${encodeURIComponent(oid)}`,{cache:'no-store'}); const j=await r.json(); slipMsg.textContent='';
    if(!j||!j.ok){ slipMsg.innerHTML='<div class="alert alert-danger">โหลดสลิปไม่สำเร็จ</div>'; return; }
    const arr=j.slips||[]; slipCountBadge.textContent=arr.length+' ไฟล์';
    if(!arr.length){ slipZone.innerHTML='<div class="text-muted">ไม่มีสลิปสำหรับออเดอร์นี้</div>'; return; }
    const frags=[]; arr.forEach((s,idx)=>{
      const raw=(s.file_path||'').replace(/^\/+/, ''); const encoded='../'+raw.split('/').map(encodeURIComponent).join('/'); const meta=`${(s.mime||'').toUpperCase()} · ${(Number(s.size_bytes||0)/1024).toFixed(0)} KB · ${escapeHtml(s.uploaded_at||'')}`; const note=(s.note||'').trim();
      frags.push(`
        <div class="slip-card">
          <a href="${encoded}" target="_blank" rel="noopener" title="เปิดไฟล์เต็ม" data-index="${idx}"><img src="${encoded}" alt="" draggable="false"></a>
          <div class="slip-meta">${meta}${note?`<div class="text-muted" style="font-size:.85rem">📝 ${escapeHtml(note)}</div>`:''}</div>
        </div>`);
    });
    slipZone.innerHTML=frags.join('');
    window.__slipImages=arr.map(s=>{ const raw=(s.file_path||'').replace(/^\/+/, ''); return '../'+raw.split('/').map(encodeURIComponent).join('/'); });
  }catch(e){ slipMsg.innerHTML='<div class="alert alert-danger">เชื่อมต่อไม่สำเร็จ</div>'; }
}
document.addEventListener('click',(e)=>{ const btn=e.target.closest('.btn-slips'); if(!btn) return; const oid=btn.getAttribute('data-oid'); if(!oid) return; loadSlips(oid); });

/* ===== Keyboard shortcuts ===== */
document.addEventListener('keydown',(e)=>{
  if(e.key === '/'){ const q=document.querySelector('input[name="q"]'); if(q){ e.preventDefault(); q.focus(); q.select(); } return; }
  const card=document.activeElement?.closest?.('.card'); if(!card) return;
  if(e.key.toLowerCase()==='r' || e.key.toLowerCase()==='x'){
    const isReady=e.key.toLowerCase()==='r';
    const form=card.querySelector(`form.js-status input[value="${isReady?'ready':'canceled'}"]`)?.closest('form'); if(form){ e.preventDefault(); form.requestSubmit(); }
  }
});
</script>

<script>
/* ===== Recipe Viewer ===== */
const recipeModal=document.getElementById('recipeModal'), recipeClose=document.getElementById('recipeClose'), rcpTitle=document.getElementById('rcpTitle'), rcpBody=document.getElementById('rcpBody');
function openRecipeModal(){ recipeModal.classList.add('is-open'); document.body.style.overflow='hidden'; }
function closeRecipeModal(){ recipeModal.classList.remove('is-open'); document.body.style.overflow=''; }
recipeClose.addEventListener('click', closeRecipeModal);
document.querySelector('#recipeModal .psu-modal__backdrop').addEventListener('click', closeRecipeModal);
document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') closeRecipeModal(); });

function fmtIngRow(name,qty,unit,note){ return `<div class="rcp-ing-row"><div>${escapeHtml(name||'')}</div><div>${escapeHtml(qty||'')}</div><div>${escapeHtml(unit||'')}</div><div>${note?'('+escapeHtml(note)+')':''}</div></div>`; }
function renderRecipe(j){
  rcpBody.innerHTML=''; if(!j||!j.ok){ rcpBody.innerHTML='<div class="alert alert-danger">โหลดสูตรไม่สำเร็จ</div>'; return; }
  const title=(j.menu&&(j.menu.title||j.menu.name))?(j.menu.title||j.menu.name):'สูตรเมนู'; rcpTitle.textContent='— '+title;
  const parts=[];
  if(Array.isArray(j.common)&&j.common.length){
    parts.push(`<div class="rcp-section"><div class="rcp-h">ส่วนผสม</div><div class="rcp-ing">
      <div class="rcp-ing-row rcp-ing-head"><div>รายการ</div><div>จำนวน</div><div>หน่วย</div><div>หมายเหตุ</div></div>
      ${j.common.map(it=>fmtIngRow(it.name,it.qty,it.unit,it.note)).join('')}
    </div></div>`);
  }
  if(Array.isArray(j.steps)&&j.steps.length){
    parts.push('<div class="rcp-section rcp-steps">');
    j.steps.forEach(step=>{
      parts.push(`<div class="rcp-step"><div class="rcp-stephead">${step.step_no}. ${escapeHtml(step.step_text||'')}</div>
        ${Array.isArray(step.ingredients)&&step.ingredients.length?`<div class="rcp-ing mt-2">${step.ingredients.map(it=>fmtIngRow(it.name,it.qty,it.unit,it.note)).join('')}</div>`:''}
      </div>`);
    });
    parts.push('</div>');
  }else{ parts.push('<div class="text-muted">ยังไม่มีขั้นตอนในสูตรนี้</div>'); }
  rcpBody.innerHTML=parts.join('');
}
async function loadRecipe(menuId){
  rcpBody.innerHTML='<div class="text-muted">กำลังโหลด...</div>'; rcpTitle.textContent=''; openRecipeModal();
  try{ const r=await fetch(`back_store.php?action=recipe&menu_id=${encodeURIComponent(menuId)}`,{cache:'no-store'}); const j=await r.json(); renderRecipe(j); }
  catch(e){ rcpBody.innerHTML='<div class="alert alert-danger">เชื่อมต่อไม่สำเร็จ</div>'; }
}
document.addEventListener('click',(e)=>{ const b=e.target.closest('.btn-recipe'); if(!b) return; const mid=b.getAttribute('data-menu-id'); if(!mid) return; loadRecipe(mid); });
</script>

<script>
/* ===== Lightbox viewer ===== */
(()=>{ const el=id=>document.getElementById(id);
  const box=el('lightbox'), img=el('lbImg'), title=el('lbTitle'), aDl=el('lbDownload');
  const btnClose=el('lbClose'), btnFit=el('lbFit'), btnIn=el('lbZoomIn'), btnOut=el('lbZoomOut');
  const btnPrev=el('lbPrev'), btnNext=el('lbNext'); const backdrop=box.querySelector('.lb__backdrop'); const wrap=box.querySelector('.lb__imgwrap');
  let list=[], i=0, scale=1, dx=0, dy=0, dragging=false, sx=0, sy=0;
  function apply(){ img.style.transform=`translate(${dx}px, ${dy}px) scale(${scale})`; }
  function fit(){ const vw=box.clientWidth, vh=box.clientHeight; const nw=img.naturalWidth||img.width, nh=img.naturalHeight||img.height;
    const rz=Math.min((vw*0.92)/nw, (vh*0.85)/nh); scale=Math.max(Math.min(rz,3),0.05); dx=dy=0; apply(); }
  function ui(){ title.textContent=`ภาพ ${i+1}/${list.length}`; aDl.href=list[i]; }
  function show(idx){ if(!list.length) return; i=(idx+list.length)%list.length; img.src=list[i]; img.onload=()=>fit(); ui(); }
  function open(idx=0){ list=Array.isArray(window.__slipImages)?window.__slipImages.slice():[]; if(!list.length) return; box.classList.add('is-open'); document.body.style.overflow='hidden'; show(idx); }
  function close(){ box.classList.remove('is-open'); document.body.style.overflow=''; }
  document.addEventListener('click',(e)=>{ const a=e.target.closest('#slipZone .slip-card a[href][data-index]'); if(!a) return; e.preventDefault(); const idx=parseInt(a.getAttribute('data-index'),10)||0; open(idx); });
  btnClose.addEventListener('click', close); backdrop.addEventListener('click', close);
  btnPrev.addEventListener('click', ()=>show(i-1)); btnNext.addEventListener('click', ()=>show(i+1));
  btnFit.addEventListener('click', fit); btnIn.addEventListener('click', ()=>{ scale=Math.min(scale*1.2,10); apply(); }); btnOut.addEventListener('click', ()=>{ scale=Math.max(scale/1.2,0.05); apply(); });
  document.addEventListener('keydown',(e)=>{ if(!box.classList.contains('is-open')) return;
    if(e.key==='Escape'){ e.preventDefault(); close(); } else if(e.key==='ArrowLeft'){ e.preventDefault(); show(i-1); }
    else if(e.key==='ArrowRight'){ e.preventDefault(); show(i+1); } else if(e.key==='+'){ scale=Math.min(scale*1.2,10); apply(); }
    else if(e.key==='-'){ scale=Math.max(scale/1.2,0.05); apply(); } else if(e.key.toLowerCase()==='f'){ fit(); }
  });
  wrap.addEventListener('wheel',(e)=>{ if(!box.classList.contains('is-open')) return; e.preventDefault(); const factor=(e.deltaY<0?1.1:1/1.1); scale=Math.max(0.05, Math.min(10, scale*factor)); apply(); },{passive:false});
  wrap.addEventListener('pointerdown',(e)=>{ if(!box.classList.contains('is-open')) return; dragging=true; wrap.setPointerCapture(e.pointerId); sx=e.clientX; sy=e.clientY; });
  wrap.addEventListener('pointermove',(e)=>{ if(!dragging) return; dx+=(e.clientX-sx); dy+=(e.clientY-sy); sx=e.clientX; sy=e.clientY; apply(); });
  wrap.addEventListener('pointerup',(e)=>{ dragging=false; wrap.releasePointerCapture(e.pointerId); });
  wrap.addEventListener('pointercancel',()=> dragging=false);
})();
</script>

</body>
</html>

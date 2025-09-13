<?php
// back_store/back_store.php — แสดงออเดอร์ + ตัวช่วยค้นหาเมนู/วันเวลา + PSU Topbar + ดูสลิป
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// จำกัดสิทธิ์ (ถ้ามี role ใน session)
$allow_roles = ['admin','employee','kitchen','back','barista'];
if (!empty($_SESSION['role']) && !in_array($_SESSION['role'], $allow_roles, true)) {
  header("Location: ../index.php"); exit;
}

/* ---------- Endpoint: ดึงสลิปตามออเดอร์ (AJAX) ---------- */
if (($_GET['action'] ?? '') === 'slips') {
  header('Content-Type: application/json; charset=utf-8');
  $oid = (int)($_GET['order_id'] ?? 0);
  if ($oid <= 0) { echo json_encode(['ok'=>false,'msg'=>'order_id ไม่ถูกต้อง']); exit; }

  $rows = [];
  if ($st = $conn->prepare("SELECT id, order_id, user_id, file_path, mime, size_bytes, uploaded_at, note
                            FROM payment_slips
                            WHERE order_id=? ORDER BY id DESC")) {
    $st->bind_param("i", $oid);
    $st->execute();
    $rs = $st->get_result();
    while($r = $rs->fetch_assoc()) $rows[] = $r;
    $st->close();
  }
  echo json_encode(['ok'=>true,'order_id'=>$oid,'slips'=>$rows]);
  exit;
}

/* ---------- อัปเดตสถานะ (รองรับ fetch/ajax และ submit ปกติ) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
  $oid = (int)$_POST['order_id'];
  $action = $_POST['action'];
  $to = $action === 'ready' ? 'ready' : ($action === 'canceled' ? 'canceled' : '');

  $ok = false;
  if ($oid > 0 && $to !== '') {
    $stmt = $conn->prepare("UPDATE orders SET status=?, updated_at=NOW() WHERE order_id=? AND status='pending'");
    $stmt->bind_param("si", $to, $oid);
    $stmt->execute();
    $ok = ($stmt->affected_rows > 0);
    $stmt->close();
  }
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $ok]); exit;
  }
  header("Location: back_store.php"); exit;
}

/* ---------- รับตัวกรองจาก GET ---------- */
$status     = $_GET['status']     ?? 'pending'; // ค่าเริ่มต้น: pending
$q          = trim((string)($_GET['q'] ?? '')); // ค้นหา “ชื่อเมนู”
$date_from  = trim((string)($_GET['date_from'] ?? ''));
$time_from  = trim((string)($_GET['time_from'] ?? ''));
$date_to    = trim((string)($_GET['date_to'] ?? ''));
$time_to    = trim((string)($_GET['time_to'] ?? ''));

// รวมวันที่+เวลา → ช่วง datetime
$dt_from = $date_from ? ($date_from . ' ' . ($time_from ?: '00:00:00')) : '';
$dt_to   = $date_to   ? ($date_to   . ' ' . ($time_to   ?: '23:59:59')) : '';

/* ---------- ดึงออเดอร์ตามเงื่อนไข (รวมจำนวนสลิป) ---------- */
$orders = [];
$where = "1=1";
$types = '';
$params = [];

if ($status !== 'all') {
  $where  .= " AND o.status = ?";
  $types  .= 's';
  $params []= $status;
}
if ($dt_from !== '') { $where .= " AND o.order_time >= ?"; $types.='s'; $params[] = $dt_from; }
if ($dt_to   !== '') { $where .= " AND o.order_time <= ?"; $types.='s'; $params[] = $dt_to; }

if ($q !== '') {
  // มีเมนูชื่อเหมือนที่ค้นหาในออเดอร์นี้หรือไม่ (ใช้ EXISTS)
  $where .= " AND EXISTS (
    SELECT 1 FROM order_details d
    JOIN menu m ON m.menu_id = d.menu_id
    WHERE d.order_id = o.order_id AND m.name LIKE ?
  )";
  $types .= 's';
  $params[] = '%'.$q.'%';
}

$sql = "
  SELECT o.order_id, o.user_id, o.order_time, o.status, o.total_price,
         u.username, u.name,
         COALESCE(ps.cnt, 0) AS slip_count
  FROM orders o
  LEFT JOIN users u ON u.user_id = o.user_id
  LEFT JOIN (
    SELECT order_id, COUNT(*) AS cnt
    FROM payment_slips
    GROUP BY order_id
  ) ps ON ps.order_id = o.order_id
  WHERE $where
  ORDER BY o.order_id DESC
";

$stmt = $conn->prepare($sql);
if ($types!=='') $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $orders[] = $row; }
$stmt->close();

/* ---------- รายการในออเดอร์ ---------- */
function get_order_lines(mysqli $conn, int $oid): array {
  $rows = [];
  $stmt = $conn->prepare("
    SELECT d.order_detail_id, d.menu_id, d.quantity, d.note, d.total_price,
           m.name AS menu_name
    FROM order_details d
    JOIN menu m ON m.menu_id = d.menu_id
    WHERE d.order_id = ?
    ORDER BY d.order_detail_id
  ");
  $stmt->bind_param("i", $oid);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $stmt->close();
  return $rows;
}
function money_fmt($n){ return number_format((float)$n, 2); }
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>หลังร้าน • ค้นหาออเดอร์/ค้างทำ</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman-blue:#0094B3;
  --psu-sky-blue:#29ABE2;  --psu-river-blue:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ok:#2e7d32; --bad:#d9534f; --ink:#1e2a3a; --card-bg:#fff; --shadow:0 10px 24px rgba(0,0,0,.14);
}
body{background:linear-gradient(135deg,var(--psu-deep-blue),var(--psu-ocean-blue));color:#fff;font-family:"Segoe UI",Tahoma,sans-serif;}
.wrap{max-width:1400px;margin:26px auto;padding:0 16px;}
.brand{font-weight:900}
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; margin:16px auto 12px;
  border-radius:14px; background:rgba(13,64,113,.92); backdrop-filter: blur(6px);
  border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18);
  max-width:1400px;
}
.topbar-actions{ gap:8px }
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }

.filter{
  background:rgba(255,255,255,.10); border:1px solid var(--psu-sritrang);
  border-radius:14px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,.18);
}
.filter label{font-weight:700; font-size:.9rem}
.filter .form-control, .filter .custom-select{ border-radius:999px; border:1px solid #d8e6ff; }
.filter .btn-find{font-weight:800; border-radius:999px}
.filter .btn-clear{border-radius:999px}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(540px,1fr));gap:18px;}
.card{background:var(--card-bg);color:var(--ink);border:1px solid #e7e9f2;border-radius:18px;box-shadow:var(--shadow);overflow:hidden;transition:.18s}
.card:hover{transform:translateY(-3px)}
.head{display:flex;justify-content:space-between;align-items:flex-start;background:#f7fbff;border-bottom:1px solid #eef0f6;padding:12px 16px}
.oid{font-weight:900;color:#0D4071}
.meta{color:#6b7280}
.line{display:flex;justify-content:space-between;padding:8px 16px;border-bottom:1px dashed #e9edf5}
.line:last-child{border-bottom:none}
.item{font-weight:800;color:#0D4071}
.note{margin-top:4px;font-size:.9rem;background:#f2f7ff;color:#123c6b;border:1px dashed #cfe0ff;border-radius:10px;padding:6px 8px}
.qty{min-width:48px;text-align:center;font-weight:900;background:#0D4071;color:#fff;border-radius:999px;padding:4px 10px}
.summary{display:flex;justify-content:space-between;padding:10px 16px 14px;font-weight:900;color:#174ea6}
.actions{display:flex;gap:10px;background:#f4f7fb;border-top:1px solid #eef0f6;padding:12px}
.btn-ready{flex:1;background:var(--ok);color:#fff;font-weight:800;border-radius:12px;padding:10px}
.btn-cancel{flex:1;background:var(--bad);color:#fff;font-weight:800;border-radius:12px;padding:10px}
.btn-slips{background:#0D4071;color:#fff;border:none;border-radius:10px;padding:6px 10px;font-weight:800}
.btn-slips[disabled]{opacity:.5; cursor:not-allowed}
.empty{background:rgba(255,255,255,.12);border:1px dashed var(--psu-sritrang);border-radius:12px;padding:24px;text-align:center}
@media (max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} .topbar-actions{width:100%; justify-content:flex-end} }

/* วิธีชำระ (chips) */
.pay-chip{
  display:inline-flex; align-items:center; gap:6px;
  border-radius:999px; font-weight:800; padding:4px 10px;
  border:1px solid #d9e6ff; background:#eef5ff; color:#0D4071; margin-top:6px;
}
.pay-chip.cash{ background:#fff8e6; border-color:#ffe1a6; color:#7a4b00; }

/* Slip modal */
.psu-modal{ position:fixed; inset:0; display:none; z-index:3000; }
.psu-modal.is-open{ display:block; }
.psu-modal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.55); backdrop-filter: blur(2px); }
.psu-modal__dialog{ position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(900px,96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:18px; box-shadow:0 22px 66px rgba(0,0,0,.4); }
.psu-modal__close{ position:absolute; right:12px; top:8px; border:0; background:transparent; font-size:32px; font-weight:900; line-height:1; cursor:pointer; color:#08345c; }
.slip-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
.slip-card{ border:1px solid #e6eefc; border-radius:12px; overflow:hidden; background:#f7fbff; }
.slip-card img{ width:100%; height:220px; object-fit:cover; display:block; background:#eaf3ff; }
.slip-meta{ padding:8px 10px; font-size:.9rem; color:#0b2b54; }
</style>
</head>
<body>

<!-- ===== Topbar ===== -->
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

  <!-- Filter bar -->
  <form class="filter mb-3" method="get">
    <div class="form-row">
      <div class="col-md-2 mb-2">
        <label>สถานะ</label>
        <select name="status" class="custom-select">
          <?php
            $opts = ['pending'=>'Pending','ready'=>'Ready','canceled'=>'Canceled','all'=>'(ทั้งหมด)'];
            foreach($opts as $k=>$v){
              $sel = ($status===$k) ? 'selected' : '';
              echo '<option value="'.htmlspecialchars($k,ENT_QUOTES).'" '.$sel.'>'.$v.'</option>';
            }
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
        <?php
          $isTransfer = ((int)$o['slip_count'] > 0);
          $pay_text   = $isTransfer ? '💳 โอนเงิน' : '💵 เงินสด';
          $pay_class  = $isTransfer ? '' : ' cash';
        ?>
        <div class="card" data-order-id="<?= (int)$o['order_id'] ?>">
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
              <!-- ปุ่มดูสลิป -->
              <button class="btn-slips btn btn-sm mt-1"
                      data-oid="<?= (int)$o['order_id'] ?>"
                      <?= ((int)$o['slip_count']>0 ? '' : 'disabled') ?>>
                ดูสลิป (<?= (int)$o['slip_count'] ?>)
              </button>
            </div>
          </div>

          <?php foreach ($lines as $ln): ?>
          <div class="line">
            <div style="padding-right:12px;max-width:78%">
              <div class="item"><?= htmlspecialchars($ln['menu_name'],ENT_QUOTES,'UTF-8') ?></div>
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

<!-- ===== Slip Modal ===== -->
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

<script>
/** ===== Poll เฉพาะกรณี: ดู pending แบบไม่ใส่ตัวกรอง ===== */
const url = new URL(location.href);
const status = url.searchParams.get('status') || 'pending';
const hasFilters =
  (url.searchParams.get('q')||'').trim() !== '' ||
  (url.searchParams.get('date_from')||'') !== '' ||
  (url.searchParams.get('date_to')||'')   !== '' ||
  (url.searchParams.get('time_from')||'') !== '' ||
  (url.searchParams.get('time_to')||'')   !== '' ||
  (status !== 'pending' && status !== null);

const FEED_URL = '../api/orders_feed.php';
const GET_URL  = (id) => '../api/order_get.php?id=' + encodeURIComponent(id);

let lastSince = '';
const knownStatus = {};

function escapeHtml(s) {
  return (s||'').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

function setPayChip(elMetaContainer, isTransfer){
  let chip = elMetaContainer.querySelector('.pay-chip');
  if(!chip){
    chip = document.createElement('div');
    chip.className = 'pay-chip';
    elMetaContainer.appendChild(chip);
  }
  chip.classList.toggle('cash', !isTransfer);
  chip.textContent = (isTransfer ? '💳 โอนเงิน' : '💵 เงินสด');
}

function renderCard(order, lines) {
  const grid = document.getElementById('grid');
  const id = order.order_id;
  let card = document.querySelector(`[data-order-id="${id}"]`);
  if (card) card.remove();

  const div = document.createElement('div');
  div.className = 'card';
  div.setAttribute('data-order-id', id);
  const slipCount = Number(order.slip_count||0);
  const isTransfer = slipCount > 0;

  div.innerHTML = `
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
    ${lines.map(ln => `
      <div class="line">
        <div style="padding-right:12px;max-width:78%">
          <div class="item">${escapeHtml(ln.menu_name)}</div>
          ${ln.note ? `<div class="note">📝 ${escapeHtml(ln.note)}</div>` : ``}
        </div>
        <div><span class="qty">x ${parseInt(ln.quantity,10)||0}</span></div>
      </div>
    `).join('')}
    <div class="summary">
      <div>ยอดรวมออเดอร์</div>
      <div>${Number(order.total_price).toFixed(2)} ฿</div>
    </div>
    ${order.status==='pending' ? `
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
    </div>` : ``}
  `;
  grid.prepend(div);
}

function hideCard(id) {
  const card = document.querySelector(`[data-order-id="${id}"]`);
  if (!card) return;
  card.style.transition = 'opacity .2s ease, transform .2s ease';
  card.style.opacity = '0';
  card.style.transform = 'translateY(-6px)';
  setTimeout(() => card.remove(), 200);
}

async function poll() {
  try {
    const qs = lastSince ? ('?since=' + encodeURIComponent(lastSince)) : '';
    const r = await fetch(FEED_URL + qs, { cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const data = await r.json();

    // อัปเดต baseline ให้เป็นเวลาล่าสุดจาก server เสมอ
    if (data.now) lastSince = data.now;

    /* ===== 1) ออเดอร์ใหม่/เปลี่ยนสถานะ ===== */
    if (Array.isArray(data.orders) && data.orders.length) {
      for (const o of data.orders) {
        const id = o.order_id;
        const st = o.status;
        const prev = knownStatus[id];
        knownStatus[id] = st;

        const card = document.querySelector(`[data-order-id="${id}"]`);
        if (st === 'pending') {
          if (card) {
            // อัปเดต badge และจำนวนสลิป + วิธีชำระ
            const badge = card.querySelector('.badge');
            if (badge) badge.textContent = 'pending';

            const btnSlip = card.querySelector('.btn-slips');
            if (btnSlip) {
              const newN = Number(o.slip_count||0);
              btnSlip.textContent = `ดูสลิป (${newN})`;
              (newN > 0) ? btnSlip.removeAttribute('disabled') : btnSlip.setAttribute('disabled','disabled');
            }
            const meta = card.querySelector('.meta');
            if (meta) setPayChip(meta, Number(o.slip_count||0) > 0);
          } else {
            // โหลดการ์ดใหม่ถ้ายังไม่มี
            try {
              const rr = await fetch(GET_URL(id), { cache:'no-store' });
              const j  = await rr.json();
              if (j && j.ok) renderCard(j.order, j.lines || []);
            } catch(e) {}
          }
        } else {
          // ready/canceled → ซ่อนการ์ด
          if (card) hideCard(id);
        }
      }
    }

    /* ===== 2) สลิปที่เพิ่งอัปโหลด (อัปเดตตัวเลขแบบ realtime) ===== */
    if (Array.isArray(data.slips) && data.slips.length) {
      for (const s of data.slips) {
        const card = document.querySelector(`[data-order-id="${s.order_id}"]`);
        if (!card) continue;
        const btn = card.querySelector('.btn-slips');
        if (btn) {
          // อ่านตัวเลขเดิม แล้ว +1
          const m = btn.textContent.match(/\((\d+)\)/);
          let n = m ? parseInt(m[1],10) : 0;
          n++;
          btn.textContent = `ดูสลิป `;
          btn.removeAttribute('disabled');
        }
        // เปลี่ยนวิธีชำระเป็นโอนเงิน
        const meta = card.querySelector('.meta');
        if (meta) setPayChip(meta, true);
      }
    }

  } catch (e) {
    // เงียบไว้ เพื่อไม่ให้รบกวน UI
  } finally {
    setTimeout(poll, 1500);
  }
}

// ส่งสถานะแบบ AJAX
document.addEventListener('submit', async (e)=>{
  const form = e.target.closest('form.js-status'); if(!form) return;
  e.preventDefault();
  const card = form.closest('[data-order-id]');
  const btn  = form.querySelector('button[type="submit"]'); if(btn) btn.disabled = true;

  try{
    const res = await fetch(location.href, {
      method:'POST',
      body:new FormData(form),
      headers:{'X-Requested-With':'XMLHttpRequest'}
    });
    const data = await res.json();
    if(data && data.ok){
      hideCard(card.getAttribute('data-order-id'));
    } else {
      alert('อัปเดตไม่สำเร็จ'); if(btn) btn.disabled=false;
    }
  }catch(err){
    alert('เชื่อมต่อไม่สำเร็จ'); if(btn) btn.disabled=false;
  }
});

// เริ่ม poll เฉพาะกรณีดู pending และไม่ตั้งตัวกรองอื่น
if (!hasFilters && status === 'pending') {
  window.addEventListener('load', poll);
}

/* ===== ดูสลิป: Modal + AJAX ===== */
const slipModal = document.getElementById('slipModal');
const slipClose = document.getElementById('slipClose');
const slipZone  = document.getElementById('slipZone');
const slipMsg   = document.getElementById('slipMsg');
const slipTitleOid = document.getElementById('slipTitleOid');
const slipCountBadge = document.getElementById('slipCountBadge');

function openSlipModal(){ slipModal.classList.add('is-open'); document.body.style.overflow='hidden'; }
function closeSlipModal(){ slipModal.classList.remove('is-open'); document.body.style.overflow=''; }
slipClose.addEventListener('click', closeSlipModal);
document.querySelector('#slipModal .psu-modal__backdrop').addEventListener('click', closeSlipModal);
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeSlipModal(); });

async function loadSlips(oid){
  slipZone.innerHTML = '';
  slipMsg.textContent = 'กำลังโหลด...';
  slipTitleOid.textContent = '#'+oid;
  slipCountBadge.textContent = '';
  openSlipModal();
  try{
    const r = await fetch(`back_store.php?action=slips&order_id=${encodeURIComponent(oid)}`, {cache:'no-store'});
    const j = await r.json();
    slipMsg.textContent = '';
    if(!j || !j.ok){ slipMsg.innerHTML = '<div class="alert alert-danger">โหลดสลิปไม่สำเร็จ</div>'; return; }
    const arr = j.slips || [];
    slipCountBadge.textContent = arr.length+' ไฟล์';
    if(!arr.length){
      slipZone.innerHTML = '<div class="text-muted">ไม่มีสลิปสำหรับออเดอร์นี้</div>';
      return;
    }
    const frags = [];
    arr.forEach(s=>{
      const p = (s.file_path||'').replace(/^\/+/, '');
      const note = (s.note||'').trim();
      const meta = `${(s.mime||'').toUpperCase()} · ${(Number(s.size_bytes||0)/1024).toFixed(0)} KB · ${escapeHtml(s.uploaded_at||'')}`;
      frags.push(`
        <div class="slip-card">
          <a href="../${encodeURIComponent(p)}" target="_blank" title="เปิดรูปเต็ม">
            <img src="../${escapeHtml(p)}" alt="">
          </a>
          <div class="slip-meta">
            <div>${meta}</div>
            ${note ? `<div class="text-muted" style="font-size:.85rem">📝 ${escapeHtml(note)}</div>`:''}
          </div>
        </div>
      `);
    });
    slipZone.innerHTML = frags.join('');
  }catch(e){
    slipMsg.innerHTML = '<div class="alert alert-danger">เชื่อมต่อไม่สำเร็จ</div>';
  }
}

// click ปุ่มดูสลิป
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-slips'); if(!btn) return;
  const oid = btn.getAttribute('data-oid'); if(!oid) return;
  loadSlips(oid);
});
</script>

</body>
</html>

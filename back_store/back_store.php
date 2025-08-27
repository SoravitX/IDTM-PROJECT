<?php
// back_store/back_store.php — แสดงเฉพาะออเดอร์สถานะ pending
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
    echo json_encode(['ok' => $ok]);
    exit;
  }
  header("Location: back_store.php"); exit;
}

/* ---------- ดึงเฉพาะ pending ---------- */
$orders = [];
$q = "
  SELECT o.order_id, o.user_id, o.order_time, o.status, o.total_price,
         u.username, u.name
  FROM orders o
  LEFT JOIN users u ON u.user_id = o.user_id
  WHERE o.status='pending'
  ORDER BY o.order_id DESC
";
$res = $conn->query($q);
while ($row = $res->fetch_assoc()) { $orders[] = $row; }
$res->close();

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
<title>หลังร้าน • ออเดอร์ค้างทำ (Pending เท่านั้น)</title>
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
.empty{background:rgba(255,255,255,.12);border:1px dashed var(--psu-sritrang);border-radius:12px;padding:24px;text-align:center}
</style>
</head>
<body>
<div class="wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="brand m-0">PSU Blue Cafe • ออเดอร์ค้างทำ</h4>
    <div>
      <a href="back_store_history.php" class="btn btn-light mr-2">ประวัติออเดอร์ (Ready/Cancel)</a>
      <a href="../logout.php" class="btn btn-outline-light">ออกจากระบบ</a>
    </div>
  </div>

  <div class="grid" id="grid">
    <?php if (empty($orders)): ?>
      <div class="empty">ยังไม่มีออเดอร์ค้างทำ</div>
    <?php else: ?>
      <?php foreach ($orders as $o): $lines = get_order_lines($conn,(int)$o['order_id']); ?>
        <div class="card" data-order-id="<?= (int)$o['order_id'] ?>">
          <div class="head">
            <div>
              <div class="oid">#<?= (int)$o['order_id'] ?> — <?= htmlspecialchars($o['username'] ?? 'user',ENT_QUOTES,'UTF-8') ?></div>
              <div class="meta"><?= htmlspecialchars($o['order_time'],ENT_QUOTES,'UTF-8') ?></div>
            </div>
            <span class="badge badge-warning p-2 font-weight-bold">pending</span>
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
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
/** ===== Real-time-ish by API polling =====
 * - โพลล์ ../api/orders_feed.php ทุก 1.5s
 * - ถ้าเจอออเดอร์ใหม่ (status='pending') แล้วยังไม่มีการ์ด => ไปโหลดเต็มจาก ../api/order_get.php?id=... แล้วแทรก DOM
 * - ถ้าเจอสถานะเปลี่ยนจาก pending => ซ่อนการ์ด (ลบออก)
 */
const FEED_URL = '../api/orders_feed.php';
const GET_URL  = (id) => '../api/order_get.php?id=' + encodeURIComponent(id);

let lastSince = '';          // pointer เวลาอัปเดตล่าสุดที่รู้อยู่
const knownStatus = {};      // จดสถานะล่าสุดต่อ order_id

// สร้าง/อัปเดตการ์ด 1 ใบ จาก order+lines
function renderCard(order, lines) {
  const grid = document.getElementById('grid');
  const id = order.order_id;
  let card = document.querySelector(`[data-order-id="${id}"]`);

  // ถ้ามีอยู่แล้ว ลบก่อนเพื่ออัปเดตใหม่ (ง่ายและชัวร์)
  if (card) card.remove();

  // สร้าง DOM แบบย่อ (ใช้สไตล์เดิมของคุณ)
  const div = document.createElement('div');
  div.className = 'card';
  div.setAttribute('data-order-id', id);
  div.innerHTML = `
    <div class="head">
      <div>
        <div class="oid">#${id} — ${escapeHtml(order.username||'user')}</div>
        <div class="meta">${escapeHtml(order.order_time)}</div>
      </div>
      <span class="badge badge-warning p-2 font-weight-bold">${escapeHtml(order.status)}</span>
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
    </div>
  `;
  grid.prepend(div); // ใหม่สุดไว้บน
}

// ซ่อน/ลบการ์ดเมื่อไม่ใช่ pending แล้ว
function hideCard(id) {
  const card = document.querySelector(`[data-order-id="${id}"]`);
  if (!card) return;
  card.style.transition = 'opacity .2s ease, transform .2s ease';
  card.style.opacity = '0';
  card.style.transform = 'translateY(-6px)';
  setTimeout(() => card.remove(), 200);
}

// escape helper
function escapeHtml(s) {
  return (s||'').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}

// โพลลิ่ง
async function poll() {
  try {
    const qs = lastSince ? ('?since=' + encodeURIComponent(lastSince)) : '';
    const r = await fetch(FEED_URL + qs, { cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const data = await r.json();

    if (!lastSince && data.now) lastSince = data.now; // ครั้งแรก

    if (Array.isArray(data.orders) && data.orders.length) {
      lastSince = data.orders[data.orders.length - 1].updated_at; // เลื่อนไปเวลาล่าสุด

      // ประมวลผลทุกรายการเปลี่ยนแปลง
      for (const o of data.orders) {
        const id = o.order_id;
        const st = o.status;
        const prev = knownStatus[id];
        knownStatus[id] = st;

        // กรณี: ออเดอร์ใหม่ (pending) -> ยังไม่มีการ์ด => ดึงข้อมูลเต็มแล้วแสดง
        if (st === 'pending') {
          const existed = !!document.querySelector(`[data-order-id="${id}"]`);
          if (!existed) {
            try {
              const rr = await fetch(GET_URL(id), { cache:'no-store' });
              const j  = await rr.json();
              if (j && j.ok) renderCard(j.order, j.lines || []);
            } catch (e) { /* เงียบไว้ก่อน */ }
          }
        } else {
          // สถานะเปลี่ยนเป็น ready/canceled => ซ่อนการ์ด
          hideCard(id);
        }
      }
    }
  } catch (e) {
    // console.warn('poll error', e);
  } finally {
    setTimeout(poll, 1500); // โพลทุก ~1.5s
  }
}
window.addEventListener('load', poll);

// ส่งสถานะแบบ AJAX (คงโค้ดเดิมของคุณ แต่เพิ่มป้องกันดับเบิลคลิก)
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
      alert('อัปเดตไม่สำเร็จ (อาจถูกอัปเดตไปแล้ว)'); if(btn) btn.disabled=false;
    }
  }catch(err){
    alert('เชื่อมต่อไม่สำเร็จ'); if(btn) btn.disabled=false;
  }
});
</script>


</body>
</html>

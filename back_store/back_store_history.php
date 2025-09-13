<?php
// SelectRole/check_order.php — แสดงออเดอร์ทั้งหมด + ดูสลิป (modal)
// รายการอาหารจะแสดง "ท็อปปิง" ก่อน "โปรโมชัน" ตามต้องการ
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money_fmt($n){ return number_format((float)$n, 2); }

/* --------- รับค่าตัวกรอง --------- */
$status    = $_GET['status']     ?? 'all';              // all | pending | ready | canceled
$q         = trim((string)($_GET['q'] ?? ''));          // ชื่อเมนู
$date_from = trim((string)($_GET['date_from'] ?? ''));
$time_from = trim((string)($_GET['time_from'] ?? ''));
$date_to   = trim((string)($_GET['date_to'] ?? ''));
$time_to   = trim((string)($_GET['time_to'] ?? ''));

$dt_from = $date_from ? ($date_from.' '.($time_from ?: '00:00:00')) : '';
$dt_to   = $date_to   ? ($date_to  .' '.($time_to   ?: '23:59:59')) : '';

/* --------- สร้างเงื่อนไข/ดึงหัวออเดอร์ --------- */
$where  = '1=1';
$types  = '';
$params = [];

if ($status !== 'all') { $where .= ' AND o.status = ?'; $types.='s'; $params[]=$status; }
if ($dt_from !== '')   { $where .= ' AND o.order_time >= ?'; $types.='s'; $params[]=$dt_from; }
if ($dt_to !== '')     { $where .= ' AND o.order_time <= ?'; $types.='s'; $params[]=$dt_to; }
if ($q !== '') {
  $where  .= " AND EXISTS(
                 SELECT 1 FROM order_details d
                 JOIN menu m ON m.menu_id=d.menu_id
                 WHERE d.order_id=o.order_id AND m.name LIKE ?
               )";
  $types  .= 's';
  $params []= '%'.$q.'%';
}

/* ดึงหัวออเดอร์ + จำนวนสลิป (slip_count) */
$sql = "
  SELECT o.order_id, o.user_id, o.order_time, o.status, o.total_price,
         u.username, u.name,
         COALESCE(ps.slip_count, 0) AS slip_count
  FROM orders o
  JOIN users u ON u.user_id=o.user_id
  LEFT JOIN (
    SELECT order_id, COUNT(*) AS slip_count
    FROM payment_slips
    GROUP BY order_id
  ) ps ON ps.order_id = o.order_id
  WHERE $where
  ORDER BY o.order_time DESC
";
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$orders_rs = $stmt->get_result();

$orders = [];
$order_ids = [];
while ($row = $orders_rs->fetch_assoc()) {
  $orders[] = $row;
  $order_ids[] = (int)$row['order_id'];
}
$stmt->close();

/* --------- ดึงรายละเอียด (พร้อมโปรโมชัน) เฉพาะออเดอร์ที่แสดง --------- */
$details = [];
if (!empty($order_ids)) {
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types_in = str_repeat('i', count($order_ids));

  // promo_id ถูกบันทึกใน order_details (อาจเป็น NULL)
  $sql2 = "
    SELECT d.order_id, d.menu_id, d.quantity, d.note, d.total_price,
           d.promo_id,
           m.name AS menu_name, m.price AS unit_base_price,
           p.name AS promo_name, p.discount_type, p.discount_value, p.max_discount
    FROM order_details d
    JOIN menu m ON m.menu_id = d.menu_id
    LEFT JOIN promotions p ON p.promo_id = d.promo_id
    WHERE d.order_id IN ($in)
    ORDER BY d.order_detail_id
  ";
  $stmt2 = $conn->prepare($sql2);
  $stmt2->bind_param($types_in, ...$order_ids);
  $stmt2->execute();
  $res2 = $stmt2->get_result();
  while ($r = $res2->fetch_assoc()) {
    // ===== คำนวณรายละเอียดราคาต่อชิ้น =====
    $qty         = max(1, (int)$r['quantity']);
    $line_total  = (float)$r['total_price'];              // รวมหลังหักโปร + ท็อปปิง
    $unit_final  = $line_total / $qty;                    // ราคาต่อชิ้นจริง
    $base_price  = (float)$r['unit_base_price'];          // ราคาเมนูฐาน

    // ส่วนลดต่อชิ้นตามโปร (ถ้ามี)
    $unit_discount = 0.0;
    if (!is_null($r['promo_id'])) {
      if ((string)$r['discount_type'] === 'PERCENT') {
        $raw = ((float)$r['discount_value']/100.0) * $base_price;
      } else {
        $raw = (float)$r['discount_value'];
      }
      $cap = is_null($r['max_discount']) ? 999999999.0 : (float)$r['max_discount'];
      $unit_discount = max(0.0, min($raw, $cap));
    }

    // ท็อปปิงต่อชิ้น (คำนวณย้อนกลับ) = unit_final - (base_price - unit_discount)
    $topping_per_unit = max(0.0, $unit_final - max(0.0, $base_price - $unit_discount));
    $topping_line     = $topping_per_unit * $qty;

    // เก็บค่าที่คำนวณเพิ่มไว้ด้วย
    $r['calc_unit_final']     = $unit_final;
    $r['calc_unit_discount']  = $unit_discount;
    $r['calc_topping_unit']   = $topping_per_unit;
    $r['calc_topping_line']   = $topping_line;

    $details[$r['order_id']][] = $r;
  }
  $stmt2->close();
}

/* --------- ดึงสลิปของออเดอร์ที่แสดง (ทั้งหมด) --------- */
$slips = []; // $slips[order_id] = [ ['path'=>..., 'uploaded_at'=>...], ... ]
if (!empty($order_ids)) {
  $in = implode(',', array_fill(0, count($order_ids), '?'));
  $types_in = str_repeat('i', count($order_ids));
  $sql3 = "
    SELECT order_id, file_path, mime, uploaded_at
    FROM payment_slips
    WHERE order_id IN ($in)
    ORDER BY uploaded_at DESC
  ";
  $stmt3 = $conn->prepare($sql3);
  $stmt3->bind_param($types_in, ...$order_ids);
  $stmt3->execute();
  $res3 = $stmt3->get_result();
  while ($r = $res3->fetch_assoc()) {
    $oid = (int)$r['order_id'];
    $url = '../' . ltrim((string)$r['file_path'], '/'); // path เก็บแบบ relative จาก root
    $slips[$oid][] = [
      'path' => $url,
      'mime' => (string)$r['mime'],
      'uploaded_at' => (string)$r['uploaded_at'],
    ];
  }
  $stmt3->close();
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เช็คออเดอร์ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<style>
:root {
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-sky-blue:#29ABE2; --psu-sritrang:#BBB4D8;
  --ok:#2e7d32; --warn:#f0ad4e; --bad:#d9534f; --shadow:0 8px 20px rgba(0,0,0,.1);
}
body{ background:linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue)); color:#fff; font-family:"Segoe UI", Tahoma, sans-serif; min-height:100vh;}
.wrap{max-width:1280px; margin:28px auto; padding:0 16px;}
.topbar{position:sticky; top:0; z-index:50; padding:12px 16px; margin-bottom:12px; border-radius:14px; background:rgba(13,64,113,.92); backdrop-filter: blur(6px); border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18);}
.brand{font-weight:900; letter-spacing:.3px; color:#fff; margin:0}
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }
.topbar-actions{ gap:8px }
.topbar .btn-primary{ background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800 }

.filter{ background:rgba(255,255,255,.10); border:1px solid var(--psu-sritrang); border-radius:14px; padding:12px; box-shadow:0 8px 18px rgba(0,0,0,.18); margin-bottom:16px;}
.filter label{font-weight:700; font-size:.9rem}
.filter .form-control, .filter .custom-select{ border-radius:999px; border:1px solid #d8e6ff }
.filter .btn-find{font-weight:800; border-radius:999px}

.grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:18px;}
.card-order{ background:#fff; color:#0D4071; border:1px solid var(--psu-sritrang); border-radius:16px; box-shadow:var(--shadow); display:flex; flex-direction:column; overflow:hidden; transition:.15s;}
.card-order:hover{ transform:translateY(-2px); box-shadow:0 12px 28px rgba(0,0,0,.18); }
.co-head{ padding:12px 16px; border-bottom:1px solid var(--psu-sritrang); background:#f6faff; display:flex; justify-content:space-between; align-items:center;}
.oid{font-weight:900; font-size:1.05rem}
.meta{font-size:.82rem; color:#275a94}
.badges{ display:flex; gap:8px; align-items:center; flex-wrap:wrap }
.badge-status{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:800; background:#fff }
.st-pending{color:var(--warn)} .st-ready{color:var(--ok)} .st-canceled{color:var(--bad)}
.badge-pay{ display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; font-size:.8rem; font-weight:800; background:#fff; color:#0D4071; border:1px solid #d8e6ff }
.pay-cash{ color:#1e6f2d } .pay-transfer{ color:#0b61a4 }
.dot{width:8px; height:8px; border-radius:50%; background:currentColor}
.co-body{padding:14px 16px; flex:1}
.line{margin-bottom:12px; font-size:.95rem; display:flex; justify-content:space-between; gap:10px}
.qtyname{font-weight:800; color:#0D4071}
.money{font-weight:900; color:#2b6fda; white-space:nowrap}
.note{ margin-top:6px; font-size:.83rem; color:#0D4071; background:#eaf4ff; border:1px solid #cfe2ff; border-radius:8px; padding:6px 8px; display:inline-block; }
.meta2{ display:flex; flex-wrap:wrap; gap:6px; margin-top:8px }
.chip{display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font-size:.8rem; font-weight:800; border:1px solid #d7e6ff;}
.chip-top{ background:#f0f7ff; color:#0d3a6a;}
.chip-promo{ background:#ecfff2; color:#0d5e2b; border-color:#cdeed5;}
.divider{border-top:1px dashed var(--psu-sritrang); margin:8px 0}
.co-foot{ background:#0D4071; color:#cde3ff; padding:12px 16px; display:flex; justify-content:space-between; align-items:center; border-radius:0 0 16px 16px }
.sum-l{font-weight:700} .sum-r{font-size:1.1rem; font-weight:900; color:#fff}

/* Modal สลิป */
#slipModalBackdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); display:none; z-index:1050; }
#slipModal{ position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:min(900px, 96vw); max-height:92vh; overflow:auto; background:#fff; border-radius:14px; box-shadow:0 22px 60px rgba(0,0,0,.45); display:none; z-index:1060; color:#0D4071;}
#slipModal .head{ display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #e5eefc; background:#f6faff; font-weight:800;}
#slipModal .body{ padding:12px; }
.slip-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap:12px;}
.slip-item{ background:#f8fbff; border:1px solid #e1edff; border-radius:10px; padding:8px; text-align:center; }
.slip-item img{ max-width:100%; height:auto; border-radius:8px; cursor:zoom-in; }
.slip-meta{ font-size:.8rem; color:#114a7a; margin-top:6px }
.btn-close-slim{ background:transparent; border:0; font-size:26px; line-height:1; cursor:pointer; color:#053157;}
.btn-view-slip{ border-radius:999px; font-weight:800; border:1px solid #cfe2ff; color:#0D4071; background:#fff;}
.btn-view-slip:hover{ background:#eef6ff; }
</style>
</head>
<body>
<div class="wrap">

  <!-- Navbar -->
  <div class="topbar d-flex align-items-center justify-content-between">
    <h4 class="brand">PSU Blue Cafe • เช็คออเดอร์</h4>
    <div class="d-flex align-items-center topbar-actions">
      <a href="back_store.php" class="btn btn-primary btn-sm">หลังร้าน</a>
      <a href="../SelectRole/role.php" class="btn btn-primary btn-sm">ตําเเหน่ง</a>
      <span class="badge badge-user px-3 py-2">ผู้ใช้: <?= h($_SESSION['username'] ?? '') ?></span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <!-- Filter -->
  <form class="filter" method="get">
    <div class="form-row">
      <div class="col-md-2 mb-2">
        <label>สถานะ</label>
        <select name="status" class="custom-select">
          <?php foreach(['all'=>'(ทั้งหมด)','pending'=>'Pending','ready'=>'Ready','canceled'=>'Canceled'] as $k=>$v){
            $sel = ($status===$k)?'selected':''; echo '<option value="'.h($k).'" '.$sel.'>'.h($v).'</option>';
          } ?>
        </select>
      </div>
      <div class="col-md-3 mb-2">
        <label>ค้นหาชื่อเมนู</label>
        <input type="text" name="q" class="form-control" value="<?= h($q) ?>" placeholder="เช่น ชาไทย">
      </div>
      <div class="col-md-3 mb-2">
        <label>ตั้งแต่ (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>"></div>
          <div class="col"><input type="time" name="time_from" class="form-control" value="<?= h($time_from) ?>"></div>
        </div>
      </div>
      <div class="col-md-3 mb-2">
        <label>ถึง (วันที่ / เวลา)</label>
        <div class="form-row">
          <div class="col"><input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>"></div>
          <div class="col"><input type="time" name="time_to" class="form-control" value="<?= h($time_to) ?>"></div>
        </div>
      </div>
      <div class="col-md-1 mb-2 d-flex align-items-end">
        <button class="btn btn-primary btn-block btn-find">ค้นหา</button>
      </div>
    </div>
    <div class="text-light small mt-1">* ใส่เฉพาะวันที่ได้ ระบบจะถือเป็น 00:00 ถึง 23:59</div>
  </form>

  <?php if(!empty($orders)): ?>
    <div class="grid">
      <?php foreach($orders as $o):
        $statusClass = ($o['status']==='ready'?'st-ready':($o['status']==='canceled'?'st-canceled':'st-pending'));
        $rows = $details[$o['order_id']] ?? [];
        $is_transfer = ((int)$o['slip_count'] > 0);
        $pay_text = $is_transfer ? 'โอนเงิน' : 'เงินสด';
        $pay_class = $is_transfer ? 'pay-transfer' : 'pay-cash';
        $oid = (int)$o['order_id'];
        $mySlips = $slips[$oid] ?? [];
      ?>
      <div class="card-order">
        <div class="co-head">
          <div>
            <div class="oid">#<?= $oid ?></div>
            <div class="meta"><?= h($o['order_time']) ?></div>
          </div>
          <div class="badges">
            <div class="badge-pay <?= $pay_class ?>" title="<?= $is_transfer ? 'มีสลิปแนบ' : 'ไม่มีสลิป (เงินสด)' ?>">
              <span class="dot"></span> <?= h($pay_text) ?>
            </div>

            <?php if (!empty($mySlips)): ?>
              <button class="btn btn-sm btn-view-slip" data-oid="<?= $oid ?>" type="button">
                ดูสลิป (<?= (int)count($mySlips) ?>)
              </button>
            <?php endif; ?>

            <div class="badge-status <?= $statusClass ?>">
              <span class="dot"></span>
              <?= h($o['status']) ?>
            </div>
          </div>
        </div>

        <div class="co-body">
          <?php if(!empty($rows)): foreach($rows as $r):
            $qty          = max(1, (int)$r['quantity']);
            $unit_final   = (float)$r['calc_unit_final'];
            $unit_disc    = (float)$r['calc_unit_discount'];
            $top_unit     = (float)$r['calc_topping_unit'];

            // ป้ายโปรแบบอ่านง่าย
            $promo_label  = '';
            if (!is_null($r['promo_id'])) {
              if ((string)$r['discount_type'] === 'PERCENT') {
                $pct = rtrim(rtrim(number_format((float)$r['discount_value'],2,'.',''), '0'), '.');
                $promo_label = $r['promo_name'] . " • ลด {$pct}% (−" . money_fmt($unit_disc) . " ฿/ชิ้น)";
              } else {
                $promo_label = $r['promo_name'] . " • ลด −" . money_fmt($unit_disc) . " ฿/ชิ้น";
              }
            }
          ?>
            <div class="line">
              <div class="flex-grow-1">
                <div class="qtyname"><?= (int)$qty ?> × <?= h($r['menu_name']) ?></div>

                <?php if(!empty($r['note'])): ?>
                  <div class="note"><?= h($r['note']) ?></div>
                <?php endif; ?>

                <!-- เรียง: ท็อปปิง -> โปรโมชัน -->
                <div class="meta2">
                  <?php if ($top_unit > 0): ?>
                    <span class="chip chip-top">ท็อปปิง +<?= money_fmt($top_unit) ?> ฿/ชิ้น</span>
                  <?php endif; ?>
                  <?php if ($promo_label !== ''): ?>
                    <span class="chip chip-promo">โปรฯ: <?= h($promo_label) ?></span>
                  <?php endif; ?>
                  <?php if ($top_unit <= 0 && $promo_label === ''): ?>
                    <span class="chip">ไม่มีโปร/ท็อปปิง</span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="money"><?= money_fmt($r['total_price']) ?> ฿</div>
            </div>
          <?php endforeach; else: ?>
            <div class="text-muted">ไม่มีรายการอาหาร</div>
          <?php endif; ?>
          <div class="divider"></div>
        </div>

        <div class="co-foot">
          <div class="sum-l">รวมทั้งออเดอร์</div>
          <div class="sum-r"><?= money_fmt($o['total_price']) ?> ฿</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-center">ไม่พบออเดอร์ตามเงื่อนไข</div>
  <?php endif; ?>

</div>

<!-- Modal แสดงสลิป -->
<div id="slipModalBackdrop"></div>
<div id="slipModal" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="head">
    <div class="ttl">สลิปการโอน • ออเดอร์ <span id="mdlOid"></span></div>
    <button class="btn-close-slim" id="btnSlipClose" aria-label="Close">&times;</button>
  </div>
  <div class="body">
    <div id="slipContainer" class="slip-grid"></div>
  </div>
</div>

<script>
(function(){
  const slipMap = <?php
    $out = [];
    foreach ($slips as $oid => $arr) {
      foreach ($arr as $s) {
        $out[$oid][] = ['path'=>$s['path'], 'uploaded_at'=>$s['uploaded_at']];
      }
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  ?>;

  const backdrop = document.getElementById('slipModalBackdrop');
  const modal    = document.getElementById('slipModal');
  const mdlOid   = document.getElementById('mdlOid');
  const listBox  = document.getElementById('slipContainer');
  const btnClose = document.getElementById('btnSlipClose');

  function openModal(oid){
    const items = slipMap[String(oid)] || slipMap[oid] || [];
    mdlOid.textContent = '#' + oid;
    listBox.innerHTML = '';
    if (!items.length) {
      listBox.innerHTML = '<div class="text-muted">ไม่มีสลิป</div>';
    } else {
      for (const it of items) {
        const card = document.createElement('div');
        card.className = 'slip-item';
        const a = document.createElement('a');
        a.href = it.path; a.target = '_blank'; a.rel = 'noopener';
        const img = document.createElement('img');
        img.src = it.path;
        a.appendChild(img);
        const meta = document.createElement('div');
        meta.className = 'slip-meta';
        meta.textContent = 'อัปโหลด: ' + (it.uploaded_at || '');
        card.appendChild(a);
        card.appendChild(meta);
        listBox.appendChild(card);
      }
    }
    backdrop.style.display = 'block';
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeModal(){
    backdrop.style.display = 'none';
    modal.style.display = 'none';
    document.body.style.overflow = '';
  }
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.btn-view-slip');
    if (!btn) return;
    const oid = btn.getAttribute('data-oid');
    openModal(oid);
  });
  backdrop.addEventListener('click', closeModal);
  btnClose.addEventListener('click', closeModal);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeModal(); });
})();
</script>

</body>
</html>

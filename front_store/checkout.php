<?php
// SelectRole/check_order.php — แสดงออเดอร์ทั้งหมดแบบการ์ดแนวตั้ง (ตกแต่งสวยงาม)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

function money_fmt($n){ return number_format((float)$n, 2); }

// ดึงหัวออเดอร์ทั้งหมด (ใหม่สุดก่อน)
$sql = "
  SELECT o.order_id, o.user_id, o.order_time, o.status, o.total_price,
         u.username, u.name
  FROM orders o
  JOIN users u ON u.user_id=o.user_id
  ORDER BY o.order_time DESC
";
$orders = $conn->query($sql);

// ดึงรายละเอียดทุกรายการ แล้วจัดกลุ่มตาม order_id
$details = [];
$res = $conn->query("
  SELECT d.order_id, d.menu_id, d.quantity, d.note, d.total_price,
         m.name AS menu_name, m.price AS unit_price
  FROM order_details d
  JOIN menu m ON m.menu_id = d.menu_id
  ORDER BY d.order_detail_id
");
while($r = $res->fetch_assoc()){
  $details[$r['order_id']][] = $r;
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
  --psu-deep-blue:    #0D4071;  /* CMYK 100 80 30 15 */
  --psu-ocean-blue:   #4173BD;  /* CMYK 80 55 0 0 */
  --psu-andaman-blue: #0094B3;  /* CMYK 85 20 20 0 */
  --psu-sky-blue:     #29ABE2;  /* CMYK 70 15 0 0 */
  --psu-river-blue:   #4EC5E0;  /* CMYK 60 0 10 0 */
  --psu-sritrang:     #BBB4D8;  /* CMYK 25 25 0 0 */

  --ok: #2e7d32;
  --warn:#f0ad4e;
  --bad:#d9534f;
  --shadow: 0 8px 20px rgba(0,0,0,.1);
}

body {
  background: linear-gradient(135deg, var(--psu-deep-blue), var(--psu-ocean-blue));
  color: #fff;
  font-family: "Segoe UI", Tahoma, sans-serif;
  min-height:100vh;
}

.wrap {max-width:1280px; margin:28px auto; padding:0 16px;}

.brand {
  font-weight:900; 
  font-size:1.4rem; 
  color: var(--psu-river-blue);
}

/* ===== Grid ===== */
.grid {
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap:20px;
}

/* ===== Card ===== */
.card-order {
  background: #fff;
  color: var(--psu-deep-blue);
  border: 1px solid var(--psu-sritrang);
  border-radius: 16px;
  box-shadow: var(--shadow);
  display:flex;
  flex-direction:column;
  overflow:hidden;
  transition:.2s;
}
.card-order:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 28px rgba(0,0,0,.18);
}

.co-head {
  padding:12px 16px; 
  border-bottom:1px solid var(--psu-sritrang);
  background: var(--psu-sky-blue);
  color:#fff;
  display:flex;
  justify-content:space-between;
  align-items:center;
}
.oid {font-weight:700; font-size:1.1rem;}
.meta {font-size:.85rem;}

.badge-status {
  display:inline-flex; align-items:center; gap:6px;
  padding:4px 10px;
  border-radius:999px;
  font-size:.8rem; font-weight:600;
  background:#fff;
}
.st-pending {color: var(--warn);}
.st-ready {color: var(--ok);}
.st-canceled {color: var(--bad);}
.dot {width:8px; height:8px; border-radius:50%; background:currentColor;}

/* body */
.co-body {padding:14px 16px; flex:1;}
.line {
  display:flex; justify-content:space-between; margin-bottom:8px;
  font-size:.95rem;
}
.note {
  margin-top:2px; font-size:.8rem;
  color: var(--psu-deep-blue);
  background: var(--psu-sritrang);
  border-radius:6px; padding:2px 6px;
  display:inline-block;
}
.money {font-weight:700; color: var(--psu-ocean-blue);}
.divider {border-top:1px dashed var(--psu-sritrang); margin:8px 0;}

/* footer */
.co-foot {
  background: var(--psu-ocean-blue);
  color:#fff;
  padding:12px 16px;
  display:flex; justify-content:space-between; align-items:center;
  border-radius:0 0 16px 16px;
}
.sum-l {font-weight:600;}
.sum-r {font-size:1.1rem; font-weight:900;}
</style>

</head>
<body>
<div class="wrap">
  <div class="topbar">
    <h3 class="brand mb-0">PSU Blue Cafe • เช็คออเดอร์</h3>
    <div class="text-right small">
      ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES,'UTF-8') ?>
    </div>
  </div>

  <?php if($orders && $orders->num_rows>0): ?>
    <div class="grid">
      <?php while($o = $orders->fetch_assoc()): 
        $statusClass = 'st-pending';
        if ($o['status']==='ready')   $statusClass = 'st-ready';
        if ($o['status']==='canceled')$statusClass = 'st-canceled';
        $rows = $details[$o['order_id']] ?? [];
      ?>
      <div class="card-order">
        <!-- Header -->
        <div class="co-head">
          <div>
            <div class="oid">#<?= (int)$o['order_id'] ?></div>
            <div class="meta"><?= htmlspecialchars($o['order_time'],ENT_QUOTES,'UTF-8') ?></div>
          </div>
          <div class="badge-status <?= $statusClass ?>">
            <span class="dot"></span>
            <?= htmlspecialchars($o['status'],ENT_QUOTES,'UTF-8') ?>
          </div>
        </div>

        <!-- Body -->
        <div class="co-body">
          <?php if(!empty($rows)): foreach($rows as $r): ?>
            <div class="line">
              <div class="left">
                <div class="qtyname">
                  <?= (int)$r['quantity'] ?> × <?= htmlspecialchars($r['menu_name'],ENT_QUOTES,'UTF-8') ?>
                </div>
                <?php if(!empty($r['note'])): ?>
                  <div class="note"><?= htmlspecialchars($r['note'],ENT_QUOTES,'UTF-8') ?></div>
                <?php endif; ?>
              </div>
              <div class="money"><?= money_fmt($r['total_price']) ?> ฿</div>
            </div>
          <?php endforeach; else: ?>
            <div class="empty">ไม่มีรายการอาหาร</div>
          <?php endif; ?>
          <div class="divider"></div>
        </div>

        <!-- Footer -->
        <div class="co-foot">
          <div class="sum-l">รวมทั้งออเดอร์</div>
          <div class="sum-r"><?= money_fmt($o['total_price']) ?> ฿</div>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="empty">ยังไม่มีออเดอร์</div>
  <?php endif; ?>
</div>
</body>
</html>

<?php
// admin/promo_create.php — สร้างโปรโมชัน + ผูกกับหลายเมนู (Font_Store Blue Theme)
// - โทนสีน้ำเงินเดียวกับหน้า font_store (#2EA7FF → #1F7EE8)
// - มี min_order_total (เฉพาะ ORDER) + max_discount + usage_limit
// - ซ่อน/แสดงฟิลด์ตาม scope
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }
// if (($_SESSION['role'] ?? '') !== 'admin') { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$message = ''; $msgClass = 'success';

/* ===== POST: Create promo ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
  $name           = trim((string)($_POST['name'] ?? ''));
  $scope          = ($_POST['scope'] ?? 'ITEM') === 'ORDER' ? 'ORDER' : 'ITEM';
  $discount_type  = ($_POST['discount_type'] ?? 'PERCENT') === 'FIXED' ? 'FIXED' : 'PERCENT';
  $discount_value = (float)($_POST['discount_value'] ?? 0);

  // optional -> NULL
  $min_order_total = (isset($_POST['min_order_total']) && $_POST['min_order_total'] !== '') ? (float)$_POST['min_order_total'] : null;
  $max_discount    = (isset($_POST['max_discount'])    && $_POST['max_discount']    !== '') ? (float)$_POST['max_discount']    : null;
  $usage_limit     = (isset($_POST['usage_limit'])     && $_POST['usage_limit']     !== '') ? (int)$_POST['usage_limit']       : null;

  $is_active = isset($_POST['is_active']) ? 1 : 0;

  $start_raw = trim((string)($_POST['start_at'] ?? ''));
  $end_raw   = trim((string)($_POST['end_at']   ?? ''));
  $start_at  = str_replace('T',' ',$start_raw);
  $end_at    = str_replace('T',' ',$end_raw);

  $menu_ids = [];
  if ($scope === 'ITEM') {
    $menu_ids = array_values(array_unique(array_map('intval', (array)($_POST['menu_ids'] ?? []))));
  }

  // validate
  if ($name===''){ $message='กรุณากรอกชื่อโปรโมชัน'; $msgClass='danger'; }
  elseif ($discount_value<=0){ $message='ส่วนลดต้องมากกว่า 0'; $msgClass='danger'; }
  elseif ($start_raw==='' || $end_raw===''){ $message='กรุณาระบุช่วงเวลาเริ่ม/สิ้นสุด'; $msgClass='danger'; }
  elseif (strtotime($end_at) <= strtotime($start_at)){ $message='เวลาสิ้นสุดต้องหลังเวลาเริ่ม'; $msgClass='danger'; }
  elseif ($scope==='ITEM' && count($menu_ids)===0){ $message='เลือกเมนูอย่างน้อย 1 รายการ'; $msgClass='danger'; }
  else{
    $stmt=$conn->prepare("
      INSERT INTO promotions
        (name, scope, discount_type, discount_value, min_order_total, max_discount,
         start_at, end_at, is_active, usage_limit, used_count, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");
    $stmt->bind_param('sssdddssii',
      $name, $scope, $discount_type, $discount_value, $min_order_total, $max_discount,
      $start_at, $end_at, $is_active, $usage_limit
    );
    $stmt->execute(); $promo_id=(int)$stmt->insert_id; $stmt->close();

    if ($scope==='ITEM' && $promo_id>0 && $menu_ids){
      $ins=$conn->prepare("INSERT IGNORE INTO promotion_items (promo_id, menu_id) VALUES (?, ?)");
      foreach($menu_ids as $mid){ $ins->bind_param('ii',$promo_id,$mid); $ins->execute(); }
      $ins->close();
    }

    $message='สร้างโปรโมชันเรียบร้อย #'.$promo_id; $msgClass='success';
  }
}

/* ===== Data ===== */
$menus = $conn->query("
  SELECT m.menu_id, m.name, m.price, c.category_name
  FROM menu m
  LEFT JOIN categories c ON m.category_id=c.category_id
  WHERE m.is_active=1
  ORDER BY c.category_name, m.name
");

$recent = $conn->query("
  SELECT p.promo_id, p.name, p.scope, p.discount_type, p.discount_value,
         p.min_order_total, p.max_discount, p.usage_limit,
         p.start_at, p.end_at, p.is_active,
         (SELECT COUNT(*) FROM promotion_items pi WHERE pi.promo_id=p.promo_id) AS item_count
  FROM promotions p
  ORDER BY p.promo_id DESC
  LIMIT 15
");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>Admin • สร้างโปรโมชัน</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
/* ===== Font_Store Blue Theme ===== */
:root{
  --text-strong:#F4F7F8;
  --text-normal:#E6EBEE;
  --text-muted:#B9C2C9;

  --bg-grad1:#1b2026;
  --bg-grad2:#252b33;

  --surface:#12171d;
  --surface-2:#171d24;
  --surface-3:#1c232b;

  --ink:#F4F7F8;

  /* เปลี่ยนโทนหลักเป็นน้ำเงินแบบ font_store */
  --brand-900:#EAF3FF;
  --brand-700:#C9DCF9;
  --brand-500:#2EA7FF;   /* primary */
  --brand-400:#1F7EE8;   /* primary darker */
  --brand-300:#73C0FF;   /* light accent */

  --ok:#22C55E;
  --danger:#E53935;

  --shadow:0 14px 34px rgba(0,0,0,.46);
  --shadow-lg:0 22px 66px rgba(0,0,0,.58);
}

html,body{height:100%}
body{
  background:
    radial-gradient(900px 360px at 110% -10%, rgba(46,167,255,.16), transparent 60%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{max-width:1100px;margin:26px auto;padding:0 14px}
.h4,.h5,h4,h5{ color:var(--brand-900) }

/* Card */
.cardx{
  background:
    linear-gradient(180deg, color-mix(in oklab, var(--surface), white 2%), color-mix(in oklab, var(--surface-2), white 2%));
  color:var(--ink);
  border:1px solid rgba(255,255,255,.06);
  border-radius:16px; box-shadow:var(--shadow);
}
.card-head{
  padding:10px 14px;
  border-bottom:1px solid rgba(255,255,255,.08);
  background:
    linear-gradient(180deg, color-mix(in oklab, var(--surface-2), white 4%), color-mix(in oklab, var(--surface-2), black 0%));
  color:var(--brand-700); font-weight:800;
}

/* Badges & Buttons */
.badge-chip{
  background:linear-gradient(180deg, rgba(46,167,255,.18), rgba(31,126,232,.14));
  color:#0B223A;
  border:1px solid rgba(31,126,232,.35);
  border-radius:999px; padding:.28rem .65rem; font-weight:900;
  text-shadow:0 1px 0 rgba(255,255,255,.25);
}
.btn-main{
  background:linear-gradient(180deg, var(--brand-500), var(--brand-400));
  border:0; font-weight:900; color:#05182b; border-radius:12px;
  box-shadow:0 10px 26px rgba(31,126,232,.32);
}
.btn-main:hover{ filter:brightness(1.05) }
.btn-outline-light{
  font-weight:800;border-radius:12px;border-color:rgba(255,255,255,.25);color:var(--text-normal)
}
.btn-outline-light:hover{background:rgba(255,255,255,.06)}

/* ปุ่มรายละเอียด โทนน้ำเงิน (ตาม font_store) */
.btn-detail{
  background: linear-gradient(180deg, var(--brand-500), var(--brand-400));
  color:#05182b; border:0; border-radius:10px; font-weight:900; padding:.25rem .6rem;
  box-shadow:0 10px 26px rgba(31,126,232,.28);
}
.btn-detail:hover{ filter:brightness(1.07); transform:translateY(-1px); }

/* Form */
label{ color:var(--brand-700); font-weight:700 }
.form-control,.custom-select{
  color:var(--text-strong); background:var(--surface-3);
  border:1.5px solid rgba(255,255,255,.10); border-radius:12px;
}
.form-control::placeholder{ color:#9aa3ab }
.form-control:focus,.custom-select:focus{
  border-color:var(--brand-500);
  box-shadow:0 0 0 .22rem rgba(46,167,255,.25);
  background:#202833;
}

/* Menu picker */
.menu-list{
  max-height:340px; overflow:auto; border:1px solid rgba(255,255,255,.08);
  border-radius:12px; background:#151c23;
}
.menu-item{
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 12px; border-bottom:1px dashed rgba(255,255,255,.08); color:var(--text-normal)
}
.menu-item:last-child{border-bottom:0}
.menu-item strong{color:var(--brand-300)}
.muted{color:var(--text-muted)}

/* Table */
.table thead th{
  background:#1a222b; color:var(--brand-300);
  border-bottom:2px solid rgba(255,255,255,.08); font-weight:800
}
.table td,.table th{ border-color:rgba(255,255,255,.06)!important; color:var(--text-normal) }
.table tbody tr:hover td{ background:#1d2630; color:var(--text-strong) }

/* Alerts */
.alert-success{background:rgba(34,197,94,.12);color:#b6f3c8;border:1px solid rgba(34,197,94,.35)}
.alert-danger {background:rgba(229,57,53,.12); color:#ffb3b1;border:1px solid rgba(229,57,53,.35)}
/* ==== White text helpers for badges ==== */
.badge-chip.badge-white { color: #fff !important; text-shadow: none !important; }
.badge-chip.badge-white a, 
.badge-chip.badge-white i { color: #fff !important; }

/* เผื่อปุ่ม/ป้ายสถานะ เปิด/ปิด */
.status-chip { color:#fff !important; text-shadow:none !important; }
/* Admin button: white text */
.btn-admin{
  background: linear-gradient(180deg, var(--brand-500), var(--brand-400));
  border: 1px solid #1e6acc;
  color:#fff !important;            /* <- เปลี่ยนเป็นขาว */
  font-weight:900;
  border-radius:12px;
  padding:.38rem .8rem;
  box-shadow:0 10px 26px rgba(31,126,232,.28);
}
.btn-admin .bi{ color:#fff !important; }   /* ไอคอนขาวด้วย */
.btn-admin:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff !important; }
.btn-admin:focus{ outline:3px solid rgba(31,126,232,.35); outline-offset:2px; color:#fff !important; }

</style>
</head>
<body>
<div class="wrap">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0 font-weight-bold"><i class="bi bi-ticket-perforated"></i> สร้างโปรโมชัน</h4>
    <div>
      <a href="adminmenu.php" class="btn btn-admin btn-sm mr-2">
  <i class="bi bi-gear"></i> เมนูแอดมิน
</a>

      <a href="../logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <?php if($message!==''): ?>
    <div class="alert alert-<?= h($msgClass) ?>"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- Form -->
  <div class="cardx mb-3">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div><i class="bi bi-sliders"></i> ตั้งค่าโปรโมชัน</div>
      <span class="badge-chip badge-white">เปอร์เซ็นต์/บาท • ITEM/ORDER</span>
    </div>
    <div class="p-3">
      <form method="post">
        <input type="hidden" name="action" value="create">

        <div class="form-row">
          <div class="form-group col-md-6">
            <label>ชื่อโปรโมชัน</label>
            <input type="text" name="name" class="form-control" required placeholder="เช่น ลดพิเศษ 10%">
          </div>

          <div class="form-group col-md-3">
            <label>Scope</label>
            <select name="scope" id="scope" class="custom-select">
              <option value="ITEM" selected>ITEM — ลดเฉพาะเมนูที่เลือก</option>
              <option value="ORDER">ORDER — ลดทั้งบิล</option>
            </select>
          </div>

          <div class="form-group col-md-3">
            <label>ประเภทส่วนลด</label>
            <select name="discount_type" class="custom-select">
              <option value="PERCENT">เปอร์เซ็นต์ (%)</option>
              <option value="FIXED">จำนวนเงิน (บาท)</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label>ค่าลด</label>
            <input type="number" name="discount_value" step="0.01" min="0" class="form-control" required placeholder="เช่น 10 หรือ 20.00">
          </div>

          <div class="form-group col-md-3">
            <label>ส่วนลดสูงสุด (บาท)</label>
            <input type="number" name="max_discount" step="0.01" min="0" class="form-control" placeholder="ไม่ระบุก็ได้">
          </div>

          <div class="form-group col-md-3" id="minOrderWrap">
            <label>ยอดขั้นต่ำทั้งบิล (บาท)</label>
            <input type="number" name="min_order_total" step="0.01" min="0" class="form-control" placeholder="ใช้เฉพาะโปรทั้งบิล">
          </div>

          <div class="form-group col-md-3">
            <label>ใช้ได้สูงสุด (ครั้ง)</label>
            <input type="number" name="usage_limit" step="1" min="0" class="form-control" placeholder="ไม่ระบุก็ได้">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group col-md-3">
            <label>เริ่ม</label>
            <input type="datetime-local" name="start_at" class="form-control" required>
          </div>
          <div class="form-group col-md-3">
            <label>สิ้นสุด</label>
            <input type="datetime-local" name="end_at" class="form-control" required>
          </div>
          <div class="form-group col-md-3 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
              <label class="form-check-label" for="is_active">เปิดใช้งานทันที</label>
            </div>
          </div>
        </div>

        <!-- เลือกเมนู (เฉพาะ ITEM) -->
        <div id="itemScopeBox" class="mb-2">
          <label class="mb-1"><i class="bi bi-list-check"></i> เลือกเมนูที่จะเข้าร่วม</label>
          <input type="text" id="menuSearch" class="form-control mb-2" placeholder="ค้นหาเมนู…">
          <div class="menu-list">
            <?php if($menus && $menus->num_rows>0): ?>
              <?php while($m=$menus->fetch_assoc()): ?>
                <label class="menu-item">
                  <span>
                    <strong><?= h($m['name']) ?></strong>
                    <small class="muted"> • หมวด: <?= h($m['category_name'] ?? '-') ?> • ราคา <?= number_format((float)$m['price'],2) ?>฿</small>
                  </span>
                  <span><input type="checkbox" name="menu_ids[]" value="<?= (int)$m['menu_id'] ?>"></span>
                </label>
              <?php endwhile; ?>
            <?php else: ?>
              <div class="p-2 text-muted">ยังไม่มีเมนู</div>
            <?php endif; ?>
          </div>
          <small class="text-muted d-block mt-1">* เลือกได้หลายรายการ</small>
        </div>

        <button class="btn btn-main"><i class="bi bi-plus-circle"></i> สร้างโปรโมชัน</button>
      </form>
    </div>
  </div>

  <!-- โปรโมชันล่าสุด -->
  <div class="cardx">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div><i class="bi bi-clock-history"></i> โปรโมชันล่าสุด</div>
      <span class="badge-chip badge-white">แสดง 15 รายการล่าสุด</span>

    </div>
    <div class="p-3">
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th style="width:60px">#</th>
              <th>ชื่อ</th>
              <th style="width:90px">Scope</th>
              <th style="width:120px">ประเภทส่วนลด</th>
              <th style="width:120px">ค่า</th>
              <th style="width:120px">Min (฿)</th>
              <th style="width:120px">MaxDisc (฿)</th>
              <th style="width:100px">Limit</th>
              <th style="width:150px">เริ่ม</th>
              <th style="width:150px">สิ้นสุด</th>
              <th style="width:90px">สถานะ</th>
              <th style="width:90px" class="text-right">#เมนู</th>
              <th style="width:110px" class="text-right">จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php if($recent && $recent->num_rows>0): ?>
              <?php while($p=$recent->fetch_assoc()): ?>
                <tr>
                  <td><?= (int)$p['promo_id'] ?></td>
                  <td><?= h($p['name']) ?></td>
                  <td>
  <span class="badge-chip <?= ($p['scope']==='ITEM'?'badge-white':'') ?>">
    <?= h($p['scope']) ?>
  </span>
</td>

                  <td><?= h($p['discount_type']) ?></td>
                  <td>
                    <?php if($p['discount_type']==='PERCENT'): ?>
                      <?= number_format((float)$p['discount_value'],2) ?>%
                    <?php else: ?>
                      <?= number_format((float)$p['discount_value'],2) ?> ฿
                    <?php endif; ?>
                  </td>
                  <td><?= $p['min_order_total']!==null ? number_format((float)$p['min_order_total'],2) : '-' ?></td>
                  <td><?= $p['max_discount']!==null   ? number_format((float)$p['max_discount'],2)   : '-' ?></td>
                  <td><?= $p['usage_limit']!==null    ? (int)$p['usage_limit']                       : '-' ?></td>
                  <td><?= h($p['start_at']) ?></td>
                  <td><?= h($p['end_at']) ?></td>
                  <td>
  <?php if((int)$p['is_active']===1): ?>
    <span class="badge-chip status-chip"
      style="background:linear-gradient(180deg, rgba(46,197,94,.22), rgba(34,197,94,.18)); border-color:rgba(34,197,94,.35)">
      เปิด
    </span>
  <?php else: ?>
    <span class="badge-chip status-chip"
      style="background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(255,255,255,.05)); border-color:rgba(255,255,255,.18)">
      ปิด
    </span>
  <?php endif; ?>
</td>

                  <td class="text-right"><?= (int)$p['item_count'] ?></td>
                  <td class="text-right">
                    <a class="btn btn-sm btn-detail" href="promo_detail.php?id=<?= (int)$p['promo_id'] ?>">รายละเอียด</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="13" class="text-center text-muted">ยังไม่มีโปรโมชัน</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
// ซ่อน/แสดงส่วนที่เกี่ยวข้องกับ scope
const scopeSel   = document.getElementById('scope');
const itemBox    = document.getElementById('itemScopeBox');
const minOrderEl = document.getElementById('minOrderWrap');

function toggleScopeBox(){
  const isItem = scopeSel.value === 'ITEM';
  if (itemBox)    itemBox.style.display    = isItem ? '' : 'none';
  if (minOrderEl) minOrderEl.style.display = isItem ? 'none' : '';
}
scopeSel?.addEventListener('change', toggleScopeBox);
toggleScopeBox();

// ค้นหาเมนูแบบสด
document.getElementById('menuSearch')?.addEventListener('input', e=>{
  const q=(e.target.value||'').toLowerCase().trim();
  document.querySelectorAll('.menu-item').forEach(li=>{
    const txt=li.textContent.toLowerCase();
    li.style.display = txt.includes(q) ? '' : 'none';
  });
});
</script>
</body>
</html>

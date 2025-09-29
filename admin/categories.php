<?php
// admin/categories.php — จัดการหมวดหมู่: เพิ่ม/แก้ไข/ลบ/เปิด-ปิด + Drag&Drop จัดลำดับ
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Ensure base columns (position, is_active) exist (optional guard) =====
try {
  $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS position INT NOT NULL DEFAULT 0");
  $conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1");
} catch (\Throwable $e) { /* host อาจไม่รองรับ IF NOT EXISTS; ข้ามได้ */ }

// ===== Handle POST actions =====
$msg=''; $cls='success';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  // 1) Create
  if ($action==='create') {
    $name = trim((string)($_POST['category_name'] ?? ''));
    if ($name==='') { $msg='กรุณากรอกชื่อหมวดหมู่'; $cls='danger'; }
    else {
      $next = (int)($conn->query("SELECT COALESCE(MAX(position),0)+1 AS n FROM categories")->fetch_assoc()['n'] ?? 1);
      $stmt = $conn->prepare("INSERT INTO categories (category_name, position, is_active) VALUES (?, ?, 1)");
      $stmt->bind_param('si', $name, $next);
      $stmt->execute(); $stmt->close();
      $msg='เพิ่มหมวดหมู่แล้ว'; $cls='success';
    }
  }

  // 2) Rename
  if ($action==='rename') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['category_name'] ?? ''));
    if ($id<=0 || $name==='') { $msg='ข้อมูลไม่ถูกต้อง'; $cls='danger'; }
    else {
      $stmt = $conn->prepare("UPDATE categories SET category_name=? WHERE category_id=?");
      $stmt->bind_param('si', $name, $id);
      $stmt->execute(); $stmt->close();
      $msg='อัปเดตชื่อหมวดหมู่เรียบร้อย'; $cls='success';
    }
  }

  // 3) Toggle active
  if ($action==='toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $conn->query("UPDATE categories SET is_active = 1 - is_active WHERE category_id = ".(int)$id);
      $msg='อัปเดตสถานะแล้ว'; $cls='success';
    }
  }

  // 4) Delete (ห้ามลบถ้ามีเมนูผูกอยู่)
  if ($action==='delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id>0) {
      $stmt=$conn->prepare("SELECT COUNT(*) c FROM menu WHERE category_id=?");
      $stmt->bind_param('i',$id); $stmt->execute();
      $c=(int)($stmt->get_result()->fetch_assoc()['c'] ?? 0); $stmt->close();
      if ($c>0) { $msg='มีเมนูอยู่ในหมวดนี้ ไม่สามารถลบได้'; $cls='danger'; }
      else {
        $stmt=$conn->prepare("DELETE FROM categories WHERE category_id=?");
        $stmt->bind_param('i',$id); $stmt->execute(); $stmt->close();
        $msg='ลบหมวดหมู่แล้ว'; $cls='success';
      }
    }
  }

  // 5) Sort (AJAX)
  if ($action==='sort') {
    $order = $_POST['order'] ?? [];
    if (is_string($order)) { $order = @json_decode($order,true) ?: []; }
    if (is_array($order) && $order) {
      $pos=1;
      $stmt=$conn->prepare("UPDATE categories SET position=? WHERE category_id=?");
      foreach ($order as $catId) {
        $id=(int)$catId; $stmt->bind_param('ii',$pos,$id); $stmt->execute(); $pos++;
      }
      $stmt->close();
    }
    header('Content-Type: application/json'); echo json_encode(['ok'=>true]); exit;
  }
}

// ===== Load categories with menu counts =====
$cats = $conn->query("
  SELECT c.category_id, c.category_name, c.position, COALESCE(c.is_active,1) AS is_active,
         (SELECT COUNT(*) FROM menu m WHERE m.category_id=c.category_id) AS menu_count
  FROM categories c
  ORDER BY c.position ASC, c.category_id ASC
");
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการหมวดหมู่ • PSU Blue Cafe</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
  href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
/* ====== FRONT_STORE THEME (น้ำเงินหลัก #3aa3ff) ====== */
:root{
  --bg1:#11161b; --bg2:#141b22;

  --surface:#1a2230; --surface-2:#192231; --surface-3:#202a3a;

  --text-strong:#ffffff; --text-normal:#e9eef6; --text-muted:#b9c6d6;

  --brand-500:#3aa3ff;   /* ฟ้าหลัก */
  --brand-400:#7cbcfd;
  --brand-300:#a9cffd;

  --ok:#22c55e; --danger:#e53935;

  --radius:10px;
}

body{
  background: linear-gradient(180deg,var(--bg1),var(--bg2));
  color:var(--text-normal);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.wrap{max-width:980px;margin:24px auto;padding:0 12px}
.h4,.h5{color:var(--text-strong)}

/* ====== Header actions ====== */
.btn-primary,
.btn-main{
  background: var(--brand-500) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important;
  font-weight:900;
  border-radius: var(--radius);
  box-shadow:none;
}
.btn-outline-light{
  background: transparent;
  color: var(--text-strong);
  border: 1px solid rgba(255,255,255,.18);
  font-weight:800;
  border-radius: var(--radius);
}
.btn-outline-light:hover{ background: var(--surface-3); }
.btn-outline-danger{
  color:#ffb4b0; border:1px solid rgba(255,0,0,.25); background:transparent; font-weight:800; border-radius:var(--radius);
}
.btn-outline-danger:disabled{ opacity:.55; cursor:not-allowed }

/* ====== Cards ====== */
.cardx{
  background: var(--surface);
  color: var(--text-normal);
  border:1px solid rgba(255,255,255,.08);
  border-radius:16px;
  box-shadow:none;
}
.card-head{
  padding:10px 14px;
  border-bottom:1px solid rgba(255,255,255,.10);
  background: var(--surface-2);
  color: var(--text-strong);
  font-weight:800;
}

/* ====== Inputs ====== */
label{ color:var(--text-strong); font-weight:700 }
.form-control{
  background: var(--surface-3);
  color: var(--text-strong);
  border:1px solid rgba(255,255,255,.12);
  border-radius:12px;
}
.form-control::placeholder{ color:#8ea2b7 }
.form-control:focus{
  border-color:#6aaeff; box-shadow:none; background: var(--surface-3); color:var(--text-strong);
}

/* ====== Helper badges ====== */
.badge-chip{
  background: var(--surface-3);
  color: var(--text-strong);
  border:1px solid rgba(255,255,255,.12);
  border-radius:999px; padding:.25rem .6rem; font-weight:800
}

/* ====== List ====== */
.list-group-item{
  background: var(--surface-3);
  color: var(--text-normal);
  border:1px solid rgba(255,255,255,.10);
  display:flex; align-items:center; justify-content:space-between;
  border-radius:12px; margin-bottom:8px;
}
.list-group-item .title{
  color: var(--text-strong);
  font-weight:900;
}
.drag-handle{ cursor:grab; opacity:.9 }
.drag-handle:active{ cursor:grabbing }

/* เม็ดบอกจำนวนเมนู */
.badge-soft{
  background: var(--surface-2);
  color:#eaf6ff;
  border:1px solid rgba(255,255,255,.12);
  border-radius:999px; padding:.2rem .6rem; font-weight:800
}

/* Alerts */
.alert-success{
  background:#0e1a12; color:#bbf7d0; border:1px solid #15803d; border-radius:12px
}
.alert-danger{
  background:#1b1111; color:#ffb4b0; border:1px solid rgba(255,0,0,.35); border-radius:12px
}

/* ปุ่มสถานะ เปิด/ปิด ให้ดูเป็นชิป */
.btn-toggle{
  background: var(--surface-2);
  border:1px solid rgba(255,255,255,.14);
  color: var(--text-strong);
  border-radius:999px;
  font-weight:900;
}
.btn-toggle.on{ background:#10331a; border-color:#17853f; color:#bff7d2 }
.btn-toggle.off{ background:#2a1a1a; border-color:#a23b3b; color:#ffc9c7 }
/* === Admin button: Front_Store blue === */
.btn-admin{
  background: linear-gradient(180deg, #56b2ff, #3aa3ff);
  border: 1px solid #1e6acc;
  color:#fff !important;
  font-weight:900;
  border-radius: var(--radius);
  padding:.38rem .8rem;
  box-shadow:0 8px 22px rgba(58,163,255,.28);
}
.btn-admin .bi{ margin-right:.35rem; }
.btn-admin:hover{ filter:brightness(1.05); transform:translateY(-1px); color:#fff !important; }
.btn-admin:focus{ outline:3px solid rgba(58,163,255,.35); outline-offset:2px; }

</style>
</head>
<body>
<div class="wrap">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0 font-weight-bold" style="color:var(--text-strong)"><i class="bi bi-columns-gap"></i> จัดการหมวดหมู่ (Categories)</h4>
    <div>
      <a href="adminmenu.php" class="btn btn-admin btn-sm mr-2">
  <i class="bi bi-gear"></i> เมนูแอดมิน
</a>

      <a href="../logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <?php if($msg!==''): ?>
    <div class="alert alert-<?= h($cls) ?>"><?= h($msg) ?></div>
  <?php endif; ?>

  <!-- Add new -->
  <div class="cardx mb-3">
    <div class="card-head"><i class="bi bi-plus-circle"></i> เพิ่มหมวดหมู่ใหม่</div>
    <div class="p-3">
      <form method="post" class="form-inline">
        <input type="hidden" name="action" value="create">
        <input type="text" name="category_name" class="form-control mr-2 mb-2" style="min-width:260px" placeholder="ชื่อหมวดหมู่ เช่น เครื่องดื่ม" required>
        <button class="btn btn-main mb-2"><i class="bi bi-plus-lg"></i> เพิ่ม</button>
      </form>
    </div>
  </div>

  <!-- List + drag sort -->
  <div class="cardx">
    <div class="card-head d-flex align-items-center justify-content-between">
      <div><i class="bi bi-list-ol"></i> เรียงลำดับ/แก้ไขหมวดหมู่</div>
      <span class="badge-chip">ลากไอคอน <i class="bi bi-grip-vertical"></i> เพื่อจัดตำแหน่ง</span>
    </div>
    <div class="p-2">
      <ul id="catList" class="list-group">
        <?php if($cats && $cats->num_rows>0): ?>
          <?php while($c=$cats->fetch_assoc()): ?>
            <li class="list-group-item" data-id="<?= (int)$c['category_id'] ?>">
              <div class="d-flex align-items-center">
                <i class="bi bi-grip-vertical mr-3 drag-handle" aria-hidden="true"></i>
                <div>
                  <div class="title"><?= h($c['category_name']) ?></div>
                  <div class="small text-muted">
                    ID: <?= (int)$c['category_id'] ?> • เมนู:
                    <span class="badge-soft"><?= (int)$c['menu_count'] ?></span>
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center">
                <form method="post" class="form-inline mr-2" onsubmit="return confirm('สลับสถานะใช้งาน?')">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$c['category_id'] ?>">
                  <?php $on = ((int)$c['is_active']===1); ?>
                  <button class="btn btn-sm btn-toggle <?= $on?'on':'off' ?>" type="submit">
                    <i class="bi <?= $on?'bi-toggle-on':'bi-toggle-off' ?>"></i>
                    <?= $on? 'เปิดอยู่' : 'ปิดอยู่' ?>
                  </button>
                </form>

                <button class="btn btn-sm btn-outline-light mr-2" onclick="openRename(<?= (int)$c['category_id'] ?>,'<?= h($c['category_name']) ?>')">
                  <i class="bi bi-pencil-square"></i> แก้ไข
                </button>

                <form method="post" class="m-0" onsubmit="return confirm('ลบหมวดหมู่นี้?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$c['category_id'] ?>">
                  <button class="btn btn-sm btn-outline-danger" <?= ((int)$c['menu_count']>0?'disabled title="มีเมนูผูกอยู่"':'') ?>>
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </div>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li class="list-group-item">ยังไม่มีหมวดหมู่</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>

  <!-- Rename box -->
  <div class="cardx mt-3" id="renameBox" style="display:none">
    <div class="card-head"><i class="bi bi-pencil"></i> เปลี่ยนชื่อหมวดหมู่</div>
    <div class="p-3">
      <form method="post" class="form-inline">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="id" id="renameId">
        <input type="text" name="category_name" id="renameName" class="form-control mr-2 mb-2" style="min-width:260px" required>
        <button class="btn btn-main mb-2"><i class="bi bi-save"></i> บันทึก</button>
        <button type="button" class="btn btn-outline-light mb-2" onclick="closeRename()">ยกเลิก</button>
      </form>
    </div>
  </div>

</div>

<script>
// Drag & sort
new Sortable(document.getElementById('catList'),{
  handle: '.drag-handle',
  animation: 150,
  onEnd: function(){
    const ids = Array.from(document.querySelectorAll('#catList .list-group-item')).map(li=>li.dataset.id);
    const form = new FormData();
    form.append('action','sort');
    form.append('order', JSON.stringify(ids));
    fetch('categories.php', { method:'POST', body:form })
      .then(r=>r.json()).catch(()=>{});
  }
});

function openRename(id, name){
  document.getElementById('renameId').value = id;
  document.getElementById('renameName').value = name;
  document.getElementById('renameBox').style.display='';
  document.getElementById('renameName').focus();
}
function closeRename(){
  document.getElementById('renameBox').style.display='none';
}
</script>
</body>
</html>

<?php
// admin_recipes.php — หน้าจัดการสูตรเมนู (Admin CRUD ครบ: หัวสูตร/ขั้นตอน/ส่วนผสม)
// ใช้ร่วมกับตาราง: recipe_headers, recipe_steps, recipe_ingredients, menu
// ขึ้นกับ db.php และ session role = admin

declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

// จำกัดเฉพาะ admin (จะเพิ่มสิทธิ์อื่นก็ได้)
if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
  header("HTTP/1.1 403 Forbidden");
  echo "Forbidden"; exit;
}

/* ---------- CSRF ---------- */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];
function check_csrf(): void {
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    header("HTTP/1.1 400 Bad Request"); echo "Bad CSRF"; exit;
  }
}

/* ---------- Utilities ---------- */
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect_self(array $qs = []): void {
  $base = strtok($_SERVER['REQUEST_URI'],'?');
  $q = http_build_query($qs);
  header('Location: '.$base.($q ? ('?'.$q):'')); exit;
}

/* ---------- Actions (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $act = $_POST['action'] ?? '';

  /* Create recipe header */
  if ($act === 'create_recipe') {
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    $title   = trim((string)($_POST['title'] ?? ''));
    if ($menu_id > 0 && $title !== '') {
      $st = $conn->prepare("INSERT INTO recipe_headers(menu_id,title) VALUES(?,?)");
      $st->bind_param('is', $menu_id, $title);
      $st->execute(); $rid = $st->insert_id; $st->close();
      redirect_self(['edit' => $rid, 'msg' => 'created']);
    }
    redirect_self(['msg' => 'invalid']);
  }

  /* Update recipe title */
  if ($act === 'update_recipe_title') {
    $rid   = (int)($_POST['recipe_id'] ?? 0);
    $title = trim((string)($_POST['title'] ?? ''));
    if ($rid > 0 && $title !== '') {
      $st = $conn->prepare("UPDATE recipe_headers SET title=?,updated_at=NOW() WHERE recipe_id=?");
      $st->bind_param('si', $title, $rid);
      $st->execute(); $st->close();
      redirect_self(['edit' => $rid, 'msg' => 'title_saved']);
    }
    redirect_self(['edit' => $rid, 'msg' => 'invalid']);
  }

  /* Delete entire recipe (with steps & ingredients) */
  if ($act === 'delete_recipe') {
    $rid = (int)($_POST['recipe_id'] ?? 0);
    if ($rid > 0) {
      // ลบ ingredients ก่อน (ทั้งของ recipe และที่ผูกกับ step)
      $st = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=?");
      $st->bind_param('i', $rid); $st->execute(); $st->close();
      // ลบ steps
      $st = $conn->prepare("DELETE FROM recipe_steps WHERE recipe_id=?");
      $st->bind_param('i', $rid); $st->execute(); $st->close();
      // ลบ header
      $st = $conn->prepare("DELETE FROM recipe_headers WHERE recipe_id=?");
      $st->bind_param('i', $rid); $st->execute(); $st->close();
      redirect_self(['msg' => 'deleted']);
    }
    redirect_self(['msg' => 'invalid']);
  }

  /* Step: add */
  if ($act === 'add_step') {
    $rid  = (int)($_POST['recipe_id'] ?? 0);
    $no   = (int)($_POST['step_no'] ?? 0);
    $text = trim((string)($_POST['step_text'] ?? ''));
    $so   = (int)($_POST['sort_order'] ?? $no);
    if ($rid>0 && $no>0 && $text!=='') {
      $st = $conn->prepare("INSERT INTO recipe_steps (recipe_id,step_no,step_text,sort_order) VALUES (?,?,?,?)");
      $st->bind_param('iisi', $rid, $no, $text, $so);
      $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'step_added']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  /* Step: update */
  if ($act === 'update_step') {
    $rid  = (int)($_POST['recipe_id'] ?? 0);
    $sid  = (int)($_POST['step_id'] ?? 0);
    $no   = (int)($_POST['step_no'] ?? 0);
    $text = trim((string)($_POST['step_text'] ?? ''));
    $so   = (int)($_POST['sort_order'] ?? $no);
    if ($rid>0 && $sid>0 && $no>0 && $text!=='') {
      $st = $conn->prepare("UPDATE recipe_steps SET step_no=?, step_text=?, sort_order=? WHERE step_id=? AND recipe_id=?");
      $st->bind_param('isiii', $no, $text, $so, $sid, $rid);
      $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'step_saved']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  /* Step: delete (and its ingredients) */
  if ($act === 'delete_step') {
    $rid = (int)($_POST['recipe_id'] ?? 0);
    $sid = (int)($_POST['step_id'] ?? 0);
    if ($rid>0 && $sid>0) {
      $st = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=? AND step_id=?");
      $st->bind_param('ii', $rid, $sid); $st->execute(); $st->close();
      $st = $conn->prepare("DELETE FROM recipe_steps WHERE step_id=? AND recipe_id=?");
      $st->bind_param('ii', $sid, $rid); $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'step_deleted']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  /* Ingredient: add */
  if ($act === 'add_ing') {
    $rid  = (int)($_POST['recipe_id'] ?? 0);
    $sid  = isset($_POST['step_id']) && $_POST['step_id']!=='' ? (int)$_POST['step_id'] : null; // null = ส่วนผสมทั่วไป
    $name = trim((string)($_POST['name'] ?? ''));
    $qty  = trim((string)($_POST['qty'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $so   = (int)($_POST['sort_order'] ?? 0);
    if ($rid>0 && $name!=='') {
      $st = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id,step_id,name,qty,unit,note,sort_order) VALUES (?,?,?,?,?,?,?)");
      // step_id อาจเป็น null -> ใช้ 'i' ธรรมดาไม่ได้ ต้องแยก bind
      // วิธีง่าย: ใช้ bind_param แบบ dynamic
      if ($sid === null) {
        $sid_null = null;
        $st->bind_param('isssssi', $rid, $sid_null, $name, $qty, $unit, $note, $so);
      } else {
        $st->bind_param('iissssi', $rid, $sid, $name, $qty, $unit, $note, $so);
      }
      // หมายเหตุ: MySQLi จะตีค่า null ถูกต้องเมื่อ type เป็น 's' หรือ 'i' แล้วตัวแปรเป็น null
      $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'ing_added']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  /* Ingredient: update */
  if ($act === 'update_ing') {
    $iid  = (int)($_POST['ingredient_id'] ?? 0);
    $rid  = (int)($_POST['recipe_id'] ?? 0);
    $sid  = ($_POST['step_id'] ?? '') !== '' ? (int)$_POST['step_id'] : null;
    $name = trim((string)($_POST['name'] ?? ''));
    $qty  = trim((string)($_POST['qty'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $so   = (int)($_POST['sort_order'] ?? 0);

    if ($iid>0 && $rid>0 && $name!=='') {
      if ($sid === null) {
        $st = $conn->prepare("UPDATE recipe_ingredients SET step_id=NULL, name=?, qty=?, unit=?, note=?, sort_order=? WHERE ingredient_id=? AND recipe_id=?");
        $st->bind_param('ssssiii', $name, $qty, $unit, $note, $so, $iid, $rid);
      } else {
        $st = $conn->prepare("UPDATE recipe_ingredients SET step_id=?, name=?, qty=?, unit=?, note=?, sort_order=? WHERE ingredient_id=? AND recipe_id=?");
        $st->bind_param('issssiii', $sid, $name, $qty, $unit, $note, $so, $iid, $rid);
      }
      $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'ing_saved']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  /* Ingredient: delete */
  if ($act === 'delete_ing') {
    $iid = (int)($_POST['ingredient_id'] ?? 0);
    $rid = (int)($_POST['recipe_id'] ?? 0);
    if ($iid>0 && $rid>0) {
      $st = $conn->prepare("DELETE FROM recipe_ingredients WHERE ingredient_id=? AND recipe_id=?");
      $st->bind_param('ii', $iid, $rid); $st->execute(); $st->close();
      redirect_self(['edit'=>$rid,'msg'=>'ing_deleted']);
    }
    redirect_self(['edit'=>$rid,'msg'=>'invalid']);
  }

  redirect_self();
}

/* ---------- GET: list or edit ---------- */
$editing_rid = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$search = trim((string)($_GET['q'] ?? ''));

/* For list view */
$recipes = [];
if (!$editing_rid) {
  $types = ''; $params = [];
  $where = '1=1';
  if ($search !== '') {
    $where .= " AND m.name LIKE ?";
    $types .= 's'; $params[] = '%'.$search.'%';
  }
  $sql = "SELECT r.recipe_id, r.menu_id, r.title, r.created_at, r.updated_at,
                 m.name AS menu_name
          FROM recipe_headers r
          JOIN menu m ON m.menu_id = r.menu_id
          WHERE $where
          ORDER BY r.recipe_id DESC";
  $st = $conn->prepare($sql);
  if ($types!=='') $st->bind_param($types, ...$params);
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) $recipes[] = $row;
  $st->close();
}

/* For edit view: load header, steps, ingredients, menu list */
$edit_header = null; $steps=[]; $ings=[]; $menu_options=[];
if ($editing_rid) {
  $st = $conn->prepare("SELECT r.*, m.name AS menu_name FROM recipe_headers r JOIN menu m ON m.menu_id=r.menu_id WHERE r.recipe_id=?");
  $st->bind_param('i', $editing_rid); $st->execute(); $edit_header = $st->get_result()->fetch_assoc(); $st->close();

  if ($edit_header) {
    $st = $conn->prepare("SELECT * FROM recipe_steps WHERE recipe_id=? ORDER BY sort_order, step_no, step_id");
    $st->bind_param('i', $editing_rid); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()) $steps[]=$r;
    $st->close();

    $st = $conn->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id=? ORDER BY COALESCE(step_id,0), sort_order, ingredient_id");
    $st->bind_param('i', $editing_rid); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()) $ings[]=$r;
    $st->close();
  }
}

/* menu list for create form */
$st = $conn->prepare("SELECT menu_id, name FROM menu WHERE is_active=1 ORDER BY name");
$st->execute(); $rs = $st->get_result();
while($r=$rs->fetch_assoc()) $menu_options[]=$r;
$st->close();

?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>จัดการสูตรเมนู (Admin)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<style>
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-sritrang:#BBB4D8;
  --ink:#10243a; --card:#fff; --accent:#29ABE2;
}
body{ background:linear-gradient(135deg,var(--psu-deep-blue),var(--psu-ocean-blue)); color:#fff; font-family:"Segoe UI",Tahoma,sans-serif; }
.wrap{ max-width:1200px; margin:26px auto; padding:0 16px; }
.topbar{ position:sticky; top:0; z-index:10; background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  border-radius:14px; margin:16px auto; padding:12px 16px; box-shadow:0 10px 26px rgba(0,0,0,.18); max-width:1200px; }
h4.brand{ margin:0; font-weight:900 }
.card-box{ background:#fff; color:var(--ink); border:1px solid #e6ecff; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.18); }
.table thead th{ background:#f4f8ff; color:#0D4071; font-weight:900; border-bottom:1px solid #e6ecff }
.badge-psu{ background:#eef5ff; color:#0D4071; border:1px solid #dbe8ff; font-weight:900; border-radius:999px; }
.form-section{ background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.35); border-radius:14px; padding:12px; margin-bottom:16px; }
a.btn-link{ color:#0D4071; font-weight:800 }
.label-blue{ color:#0D4071; font-weight:900 }
.btn-psu{ background:#0D4071; color:#fff; font-weight:900; border-radius:10px }
.btn-psu:hover{ filter:brightness(1.05) }
.small-note{ color:#6b7a90; font-size:.9rem }
hr.sep{ border-top:1px dashed #dfe6ff }
.step-tag{ display:inline-block; min-width:32px; text-align:center; background:#0D4071; color:#fff; border-radius:999px; padding:2px 8px; font-weight:900 }
.code{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
</style>
</head>
<body>

<div class="topbar d-flex align-items-center justify-content-between">
  <h4 class="brand">PSU Blue Cafe • จัดการสูตรเมนู (Admin)</h4>
  <div>
    <a href="../SelectRole/role.php" class="btn btn-sm btn-light mr-2">ตําเเหน่ง</a>
    <a href="../logout.php" class="btn btn-sm btn-outline-light">ออกจากระบบ</a>
  </div>
</div>

<div class="wrap">
  <?php if (!$editing_rid): ?>
  <!-- ===== List View ===== -->
  <div class="mb-3 form-section">
    <form class="form-inline">
      <label class="mr-2 mb-2">ค้นหาชื่อเมนู</label>
      <input type="text" name="q" class="form-control mr-2 mb-2" placeholder="เช่น มัทฉะ / ชาไทย" value="<?= e($search) ?>">
      <button class="btn btn-psu mb-2">ค้นหา</button>
      <a href="admin_recipes.php" class="btn btn-light ml-2 mb-2">ล้าง</a>
    </form>
  </div>

  <div class="card-box p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="m-0 label-blue">รายการสูตรทั้งหมด</h5>
      <button class="btn btn-psu" data-toggle="collapse" data-target="#newRecipe">+ เพิ่มสูตรใหม่</button>
    </div>

    <!-- Add new recipe -->
    <div id="newRecipe" class="collapse">
      <form method="post" class="border rounded p-3 mb-3">
        <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
        <input type="hidden" name="action" value="create_recipe">
        <div class="form-row">
          <div class="col-md-4 mb-2">
            <label class="label-blue">เลือกเมนู</label>
            <select name="menu_id" class="form-control" required>
              <option value="">— เลือก —</option>
              <?php foreach ($menu_options as $m): ?>
                <option value="<?= (int)$m['menu_id'] ?>"><?= e($m['name']) ?> (ID: <?= (int)$m['menu_id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label class="label-blue">หัวสูตร / ชื่อสูตร</label>
            <input type="text" name="title" class="form-control" placeholder="เช่น มัทฉะลาเต้ (Iced Matcha Latte)" required>
          </div>
          <div class="col-md-2 mb-2 d-flex align-items-end">
            <button class="btn btn-psu btn-block">บันทึก</button>
          </div>
        </div>
        <div class="small-note">* 1 เมนูอาจมีหลายชุดสูตรได้ ระบบจะใช้สูตรล่าสุดเป็นค่าเริ่มต้น</div>
      </form>
      <hr class="sep">
    </div>

    <?php if (empty($recipes)): ?>
      <div class="text-muted">ยังไม่มีสูตร หรือไม่พบตามคำค้น</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
         <thead><tr>
  <th>ID</th><th>เมนู</th><th>หัวสูตร</th><th class="text-right">จัดการ</th>
</tr></thead>
<tbody>
<?php foreach ($recipes as $r): ?>
  <tr>
    <td class="code">#<?= (int)$r['recipe_id'] ?></td>
    <td><?= e($r['menu_name']) ?></td>
    <td><?= e($r['title']) ?></td>
    <td class="text-right" style="white-space:nowrap">

                <a href="?edit=<?= (int)$r['recipe_id'] ?>" class="btn btn-sm btn-primary">แก้ไข</a>
                <form method="post" class="d-inline" onsubmit="return confirm('ลบสูตรนี้และข้อมูลย่อยทั้งหมด?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="delete_recipe">
                  <input type="hidden" name="recipe_id" value="<?= (int)$r['recipe_id'] ?>">
                  <button class="btn btn-sm btn-danger">ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ===== Edit View ===== -->
  <?php if (!$edit_header): ?>
    <div class="card-box p-3">ไม่พบสูตรที่ต้องการแก้ไข</div>
  <?php else: ?>
  <div class="card-box p-3 mb-3">
    <a href="admin_recipes.php" class="btn btn-light mb-2">← กลับรายการสูตร</a>
    <h5 class="label-blue mb-2">แก้ไขสูตร: <span class="badge badge-psu">Recipe #<?= (int)$edit_header['recipe_id'] ?></span></h5>
    <div class="mb-2">เมนู: <strong><?= e($edit_header['menu_name']) ?></strong> <span class="text-muted">(menu_id <?= (int)$edit_header['menu_id'] ?>)</span></div>

    <!-- Edit recipe title -->
    <form method="post" class="border rounded p-3 mb-3">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="update_recipe_title">
      <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
      <div class="form-row">
        <div class="col-md-9 mb-2">
          <label class="label-blue">หัวสูตร / ชื่อสูตร</label>
          <input type="text" name="title" class="form-control" value="<?= e($edit_header['title'] ?? '') ?>" required>
        </div>
        <div class="col-md-3 mb-2 d-flex align-items-end">
          <button class="btn btn-psu btn-block">บันทึกชื่อสูตร</button>
        </div>
      </div>
      <div class="text-muted small">สร้างเมื่อ: <?= e($edit_header['created_at']) ?> • ปรับปรุงล่าสุด: <?= e($edit_header['updated_at']) ?></div>
    </form>

    <!-- Steps -->
    <h6 class="label-blue">ขั้นตอน (Steps)</h6>
    <div class="table-responsive mb-2">
      <table class="table table-sm">
        <thead><tr><th>#</th><th>Step No.</th><th>คำอธิบาย</th><th>Sort</th><th class="text-right">จัดการ</th></tr></thead>
        <tbody>
          <?php if (empty($steps)): ?>
            <tr><td colspan="5" class="text-muted">ยังไม่มีขั้นตอน</td></tr>
          <?php else: foreach ($steps as $s): ?>
            <tr>
              <td class="code">#<?= (int)$s['step_id'] ?></td>
              <td>
                <form method="post" class="form-inline">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="update_step">
                  <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
                  <input type="hidden" name="step_id" value="<?= (int)$s['step_id'] ?>">
                  <input type="number" name="step_no" class="form-control form-control-sm" style="width:80px" value="<?= (int)$s['step_no'] ?>">
              </td>
              <td>
                  <input type="text" name="step_text" class="form-control form-control-sm" style="width:100%" value="<?= e($s['step_text']) ?>">
              </td>
              <td style="width:120px">
                  <input type="number" name="sort_order" class="form-control form-control-sm" style="width:100px" value="<?= (int)$s['sort_order'] ?>">
              </td>
              <td class="text-right">
                  <button class="btn btn-sm btn-primary">บันทึก</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('ลบขั้นตอนนี้ (และส่วนผสมที่ผูกกับขั้นตอนนี้) ?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="delete_step">
                  <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
                  <input type="hidden" name="step_id" value="<?= (int)$s['step_id'] ?>">
                  <button class="btn btn-sm btn-danger">ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Add step -->
    <form method="post" class="border rounded p-3 mb-3">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="add_step">
      <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
      <div class="form-row">
        <div class="col-md-2 mb-2"><label class="label-blue">Step No.</label><input type="number" name="step_no" class="form-control" required></div>
        <div class="col-md-7 mb-2"><label class="label-blue">คำอธิบาย</label><input type="text" name="step_text" class="form-control" required></div>
        <div class="col-md-2 mb-2"><label class="label-blue">Sort</label><input type="number" name="sort_order" class="form-control"></div>
        <div class="col-md-1 mb-2 d-flex align-items-end"><button class="btn btn-psu btn-block">เพิ่ม</button></div>
      </div>
    </form>

    <!-- Ingredients -->
    <h6 class="label-blue">ส่วนผสม (Ingredients)</h6>
    <div class="table-responsive mb-2">
      <table class="table table-sm">
        <thead><tr>
          <th>#</th><th>ผูก Step</th><th>ชื่อวัตถุดิบ</th><th>Qty</th><th>Unit</th><th>Note</th><th>Sort</th><th class="text-right">จัดการ</th>
        </tr></thead>
        <tbody>
          <?php if (empty($ings)): ?>
            <tr><td colspan="8" class="text-muted">ยังไม่มีส่วนผสม</td></tr>
          <?php else: foreach ($ings as $i): ?>
            <tr>
              <td class="code">#<?= (int)$i['ingredient_id'] ?></td>
              <td style="width:150px">
                <form method="post" class="form-inline">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="update_ing">
                  <input type="hidden" name="ingredient_id" value="<?= (int)$i['ingredient_id'] ?>">
                  <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
                  <select name="step_id" class="form-control form-control-sm" style="width:140px">
                    <option value="" <?= $i['step_id']===null?'selected':''?>>(ทั่วไป)</option>
                    <?php foreach ($steps as $s): ?>
                      <option value="<?= (int)$s['step_id'] ?>" <?= ((int)$i['step_id']===(int)$s['step_id'])?'selected':'' ?>>
                        #<?= (int)$s['step_no'] ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
              </td>
              <td><input type="text" name="name" class="form-control form-control-sm" value="<?= e($i['name']) ?>"></td>
              <td style="width:120px"><input type="text" name="qty" class="form-control form-control-sm" value="<?= e((string)$i['qty']) ?>"></td>
              <td style="width:120px"><input type="text" name="unit" class="form-control form-control-sm" value="<?= e((string)$i['unit']) ?>"></td>
              <td><input type="text" name="note" class="form-control form-control-sm" value="<?= e((string)$i['note']) ?>"></td>
              <td style="width:90px"><input type="number" name="sort_order" class="form-control form-control-sm" value="<?= (int)$i['sort_order'] ?>"></td>
              <td class="text-right">
                <button class="btn btn-sm btn-primary">บันทึก</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('ลบส่วนผสมนี้?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="delete_ing">
                  <input type="hidden" name="ingredient_id" value="<?= (int)$i['ingredient_id'] ?>">
                  <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
                  <button class="btn btn-sm btn-danger">ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Add ingredient -->
    <form method="post" class="border rounded p-3 mb-1">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="add_ing">
      <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
      <div class="form-row">
        <div class="col-md-2 mb-2">
          <label class="label-blue">ผูก Step</label>
          <select name="step_id" class="form-control">
            <option value="">(ทั่วไป)</option>
            <?php foreach ($steps as $s): ?>
              <option value="<?= (int)$s['step_id'] ?>">#<?= (int)$s['step_no'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <label class="label-blue">ชื่อวัตถุดิบ</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-2 mb-2">
          <label class="label-blue">Qty</label>
          <input type="text" name="qty" class="form-control" placeholder="เช่น 30 / Fill-up">
        </div>
        <div class="col-md-2 mb-2">
          <label class="label-blue">Unit</label>
          <input type="text" name="unit" class="form-control" placeholder="ml / Teaspoon">
        </div>
        <div class="col-md-2 mb-2">
          <label class="label-blue">Sort</label>
          <input type="number" name="sort_order" class="form-control" value="0">
        </div>
        <div class="col-md-1 mb-2 d-flex align-items-end">
          <button class="btn btn-psu btn-block">เพิ่ม</button>
        </div>
        <div class="col-12">
          <label class="label-blue">Note</label>
          <input type="text" name="note" class="form-control" placeholder="เช่น นมข้นหวาน / ใส่จนเต็มแก้ว">
        </div>
      </div>
    </form>

  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>

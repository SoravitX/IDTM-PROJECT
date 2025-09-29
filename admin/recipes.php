<?php
// admin_recipes.php — หน้าจัดการสูตรเมนู (Admin CRUD ครบ: หัวสูตร/ขั้นตอน/ส่วนผสม)
// ใช้ร่วมกับตาราง: recipe_headers, recipe_steps, recipe_ingredients, menu
// ขึ้นกับ db.php และ session role = admin

declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// จำกัดเฉพาะ admin
if (($_SESSION['role'] ?? '') !== 'admin') {
  $_SESSION['flash'] = 'ต้องเป็นแอดมินเท่านั้น';
  header("Location: ../SelectRole/role.php");  // หรือหน้า dashboard
  exit;
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
      $st = $conn->prepare("DELETE FROM recipe_ingredients WHERE recipe_id=?");
      $st->bind_param('i', $rid); $st->execute(); $st->close();

      $st = $conn->prepare("DELETE FROM recipe_steps WHERE recipe_id=?");
      $st->bind_param('i', $rid); $st->execute(); $st->close();

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

  /* Step: delete */
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
    $sid  = isset($_POST['step_id']) && $_POST['step_id']!=='' ? (int)$_POST['step_id'] : null;
    $name = trim((string)($_POST['name'] ?? ''));
    $qty  = trim((string)($_POST['qty'] ?? ''));
    $unit = trim((string)($_POST['unit'] ?? ''));
    $note = trim((string)($_POST['note'] ?? ''));
    $so   = (int)($_POST['sort_order'] ?? 0);
    if ($rid>0 && $name!=='') {
      $st = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id,step_id,name,qty,unit,note,sort_order) VALUES (?,?,?,?,?,?,?)");
      if ($sid === null) {
        $sid_null = null;
        $st->bind_param('isssssi', $rid, $sid_null, $name, $qty, $unit, $note, $so);
      } else {
        $st->bind_param('iissssi', $rid, $sid, $name, $qty, $unit, $note, $so);
      }
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
<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

<style>
/* ====== Theme Tokens: Teal-Graphite ====== */
:root{
  --text-strong:#F4F7F8;
  --text-normal:#E6EBEE;
  --text-muted:#B9C2C9;

  --bg-grad1:#222831;     /* background */
  --bg-grad2:#393E46;

  --surface:#1C2228;      /* cards */
  --surface-2:#232A31;
  --surface-3:#2B323A;

  --ink:#F4F7F8;
  --ink-muted:#CFEAED;

  --brand-900:#EEEEEE;
  --brand-700:#BFC6CC;
  --brand-500:#00ADB5;    /* accent */
  --brand-400:#27C8CF;
  --brand-300:#73E2E6;

  --ok:#2ecc71; --danger:#e53935;

  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
  --shadow:   0 14px 32px rgba(0,0,0,.42);
}

/* ====== Base ====== */
html,body{height:100%}
body{
  background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--ink); font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
a{ color:var(--brand-300) }
a:hover{ color:var(--brand-400) }
.wrap{ max-width:1200px; margin:26px auto; padding:0 16px; }

/* ====== Topbar ====== */
.topbar{
  position:sticky; top:0; z-index:10;
  background: color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:14px; padding:12px 16px; margin:16px auto;
  box-shadow:var(--shadow); backdrop-filter: blur(6px);
  max-width:1200px;
}
.brand{ margin:0; font-weight:900; letter-spacing:.3px; color:var(--brand-900) }
.brand .bi{ margin-right:8px; opacity:.95 }

/* ====== Cards / Sections ====== */
.card-box,.cardx{
  background: linear-gradient(180deg,var(--surface),var(--surface-2));
  color:var(--ink);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:16px; box-shadow:var(--shadow);
}
.form-section{
  background: color-mix(in oklab, var(--surface), white 6%);
  border:1px solid color-mix(in oklab, var(--brand-700), black 18%);
  border-radius:14px; padding:12px; margin-bottom:16px;
}

/* ====== Buttons ====== */
.btn-psu, .btn-primary{
  background: linear-gradient(180deg, var(--brand-500), var(--brand-400));
  border:1px solid color-mix(in oklab, var(--brand-500), black 25%);
  color:#062b33; font-weight:900; border-radius:12px; letter-spacing:.2px;
  box-shadow:0 10px 22px rgba(0,0,0,.25);
}
.btn-psu:hover,.btn-primary:hover{ filter:brightness(1.05) }
.btn-light{ background:var(--surface-2); color:var(--ink); border:1px solid color-mix(in oklab, var(--brand-700), black 15%); }
.btn-outline-light{ color:var(--brand-900); border-color: color-mix(in oklab, var(--brand-700), black 25%); }
.btn-danger{ background:linear-gradient(180deg,#ff6b6b,#e94444); border:1px solid #c22f2f; color:#2a0202; font-weight:900; border-radius:12px; }

/* ====== Table ====== */
.table{ color:var(--text-normal) }
.table thead th{
  background: var(--surface-3); color:var(--brand-300);
  border-bottom:2px solid color-mix(in oklab, var(--brand-700), black 18%); font-weight:900;
}
.table td, .table th{ border-color: color-mix(in oklab, var(--brand-700), black 22%) !important; }
.table tbody tr:hover td{ background: color-mix(in oklab, var(--surface-3), white 4%) }

/* ====== Forms ====== */
label{ font-weight:800; color:var(--brand-700) }
.form-control, .custom-select{
  background: var(--surface-3); color:var(--ink);
  border:1.5px solid color-mix(in oklab, var(--brand-700), black 22%);
  border-radius:12px;
}
.form-control::placeholder{ color:var(--text-muted) }
.form-control:focus{
  border-color: var(--brand-400);
  box-shadow: 0 0 0 .2rem color-mix(in oklab, var(--brand-300), white 40%);
  background: #2F373F;
}

/* ====== Badges / Chips ====== */
.badge-psu{
  background: color-mix(in oklab, var(--brand-400), black 80%);
  color: var(--brand-900);
  border:1px solid color-mix(in oklab, var(--brand-700), black 30%);
  font-weight:900; border-radius:999px;
}
.label-blue{ color:var(--brand-900); font-weight:900 }
.code{ font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; color:var(--brand-300) }

/* ====== Misc ====== */
hr.sep{ border-top:1px dashed color-mix(in oklab, var(--brand-700), black 25%) }
.text-muted{ color:var(--text-muted)!important }
:focus-visible{ outline:3px solid color-mix(in oklab, var(--brand-400), white 40%); outline-offset:2px; border-radius:10px }
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:color-mix(in oklab, var(--brand-700), black 10%);border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:color-mix(in oklab, var(--brand-500), black 12%)}
*::-webkit-scrollbar-track{background:var(--surface-2)}
/* === PSU Dark Teal (match font_store) — drop-in overrides === */

/* พาเลตหลัก */
:root{
  --text-strong:#F4F7F8; --text-normal:#E6EBEE; --text-muted:#B9C2C9;
  --bg-grad1:#222831; --bg-grad2:#393E46;
  --surface:#1C2228; --surface-2:#232A31; --surface-3:#2B323A;
  --ink:#F4F7F8; --ink-muted:#CFEAED;
  --brand-900:#EEEEEE; --brand-700:#BFC6CC;
  --brand-500:#00ADB5; --brand-400:#27C8CF; --brand-300:#73E2E6;
  --ok:#22c55e; --danger:#e53935;
  --shadow:0 14px 32px rgba(0,0,0,.42);
  --shadow-lg:0 22px 66px rgba(0,0,0,.55);
}

/* พื้นหลังนุ่ม +ไฮไลต์มุมบนขวา */
body{
  background:
    radial-gradient(900px 360px at 110% -10%, rgba(39,200,207,.16), transparent 60%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2)) !important;
  color:var(--text-normal);
}

/* Topbar ให้เหมือน font_store */
.topbar{
  background:rgba(35,42,49,.92) !important;
  border:1px solid rgba(255,255,255,.08) !important;
  box-shadow:var(--shadow) !important;
  backdrop-filter: blur(6px);
}
.brand{ color:var(--brand-900) !important; }

/* การ์ด/กล่อง */
.card-box,.cardx,.form-section{
  background:linear-gradient(180deg,var(--surface),var(--surface-2)) !important;
  border:1px solid rgba(255,255,255,.08) !important;
  color:var(--ink) !important;
  border-radius:16px !important;
  box-shadow:var(--shadow) !important;
}

/* ปุ่ม */
.btn-primary,.btn-psu{
  background:linear-gradient(180deg,var(--brand-400),var(--brand-500)) !important;
  border:1px solid var(--brand-500) !important;
  color:#062e31 !important; font-weight:900 !important; border-radius:12px !important;
}
.btn-primary:hover,.btn-psu:hover{ filter:brightness(1.05); }
.btn-light{
  background:var(--surface-2) !important; color:var(--ink) !important;
  border:1px solid rgba(255,255,255,.12) !important;
}
.btn-outline-light{
  color:var(--brand-900) !important; border-color:rgba(255,255,255,.25) !important;
}
.btn-danger{
  background:linear-gradient(180deg,#ff6b6b,#e94444)!important;
  border:1px solid #c22f2f!important; color:#2a0202!important; font-weight:900!important;
  border-radius:12px!important;
}

/* ฟอร์ม */
label{ color:var(--brand-700) !important; font-weight:800 !important; }
.form-control,.custom-select{
  background:var(--surface-3) !important; color:var(--ink) !important;
  border:1.5px solid rgba(255,255,255,.12) !important; border-radius:12px !important;
}
.form-control::placeholder{ color:#9aa3ab; }
.form-control:focus,.custom-select:focus{
  border-color:var(--brand-400) !important;
  box-shadow:0 0 0 .2rem rgba(39,200,207,.22) !important;
}

/* ตาราง */
.table{ color:var(--text-normal) !important; }
.table thead th{
  background:#222a31 !important; color:var(--brand-300) !important;
  border-bottom:2px solid rgba(255,255,255,.10) !important; font-weight:900 !important;
}
.table td,.table th{ border-color:rgba(255,255,255,.08) !important; }
.table tbody tr:hover td{ background:rgba(255,255,255,.03) !important; }

/* ป้าย/ชิป */
.badge-psu{
  background: color-mix(in oklab, var(--brand-400), black 78%) !important;
  color: var(--brand-900) !important; border:1px solid rgba(255,255,255,.18) !important;
  font-weight:900; border-radius:999px;
}
.label-blue{ color:var(--brand-900) !important; font-weight:900 !important; }

/* เส้นคั่น/โฟกัส/สกรอลล์บาร์ */
hr.sep{ border-top:1px dashed rgba(255,255,255,.15) !important; }
:focus-visible{ outline:3px solid rgba(39,200,207,.35); outline-offset:2px; border-radius:10px; }
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2a323a;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#33404a}
*::-webkit-scrollbar-track{background:#141a1f}

/* ตัวหนังสือหัวข้อย่อย */
h5,h6{ color:var(--brand-900) !important; }
/* === Fix: text color on table hover (dark theme) === */
.table-hover tbody tr:hover {
  color: var(--text-strong) !important;          /* สว่างชัด */
}
.table-hover tbody tr:hover td,
.table-hover tbody tr:hover th {
  color: var(--text-strong) !important;          /* บังคับทุกเซลล์ */
  background: rgba(255,255,255,.03) !important;  /* คงพื้นหลังโทนเดิม */
}

/* ลิงก์/ป้ายในแถว hover ให้อ่านง่าย */
.table-hover tbody tr:hover a {
  color: var(--brand-300) !important;
}
.table-hover tbody tr:hover .text-muted {
  color: var(--ink-muted) !important;            /* muted แต่ยังมองเห็น */
  opacity: .95;
}

/* ถ้ามี badge หรือ code ที่เป็นสีอ่อน ให้ยังคงมองเห็น */
.table-hover tbody tr:hover .badge,
.table-hover tbody tr:hover .code {
  color: inherit !important;
}
/* Admin menu button — force white text */
.btn-admin{
  background: linear-gradient(180deg, var(--brand-500), var(--brand-400)) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important;                 /* ตัวอักษรขาว */
  font-weight:900; border-radius:12px;
  padding:.38rem .8rem;
  box-shadow:0 10px 26px rgba(0,0,0,.25);
}
.btn-admin .bi{ color:#fff !important; } /* ไอคอนขาวด้วย */
.btn-admin:hover,
.btn-admin:focus{ color:#fff !important; filter:brightness(1.05); }

/* เผื่อกรณีไม่ได้ใส่คลาส .btn-admin แต่ลิงก์ไป adminmenu.php */
a[href$="adminmenu.php"].btn{
  background: linear-gradient(180deg, var(--brand-500), var(--brand-400)) !important;
  border: 1px solid #1e6acc !important;
  color:#fff !important;
}
a[href$="adminmenu.php"].btn .bi{ color:#fff !important; }
/* ปุ่มเมนูแอดมิน — โทนฟ้าตามตัวอย่าง */
.btn-admin,
a[href$="adminmenu.php"].btn {
  background: linear-gradient(180deg, #3AA3FF, #1F7EE8) !important;
  border: 1px solid #1E6ACC !important;
  color: #fff !important;                 /* ตัวอักษรขาว */
  font-weight: 900;
  border-radius: 999px;                    /* โค้งแบบเม็ดถั่ว */
  padding: .45rem .95rem;
  box-shadow: 0 10px 26px rgba(31,126,232,.28);
}
.btn-admin .bi,
a[href$="adminmenu.php"].btn .bi { color:#fff !important; }

.btn-admin:hover,
a[href$="adminmenu.php"].btn:hover {
  filter: brightness(1.05);
  color:#fff !important;
}

.btn-admin:focus,
a[href$="adminmenu.php"].btn:focus {
  outline: 3px solid rgba(58,163,255,.35);
  outline-offset: 2px;
}

</style>
</head>
<body>

<div class="topbar d-flex align-items-center justify-content-between">
  <h4 class="brand"><i class="bi bi-journal-text"></i> PSU Blue Cafe • จัดการสูตรเมนู (Admin)</h4>
  <div class="d-flex align-items-center">
    <a href="adminmenu.php" class="btn btn-primary btn-sm mr-2 btn-admin">
  <i class="bi bi-gear"></i> เมนูแอดมิน
</a>
    <a href="../logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
  </div>
</div>

<div class="wrap">
  <?php if (!$editing_rid): ?>
  <!-- ===== List View ===== -->
  <div class="form-section">
    <form class="form-inline">
      <label class="mr-2 mb-2"><i class="bi bi-search"></i> ค้นหาชื่อเมนู</label>
      <input type="text" name="q" class="form-control mr-2 mb-2" placeholder="เช่น มัทฉะ / ชาไทย" value="<?= e($search) ?>">
      <button class="btn btn-psu mb-2"><i class="bi bi-arrow-right-circle"></i> ค้นหา</button>
      <a href="admin_recipes.php" class="btn btn-light ml-2 mb-2">ล้าง</a>
    </form>
  </div>

  <div class="card-box p-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="m-0 label-blue"><i class="bi bi-collection"></i> รายการสูตรทั้งหมด</h5>
      <button class="btn btn-psu" data-toggle="collapse" data-target="#newRecipe"><i class="bi bi-plus-circle"></i> เพิ่มสูตรใหม่</button>
    </div>

    <!-- Add new recipe -->
    <div id="newRecipe" class="collapse">
      <form method="post" class="border rounded p-3 mb-3" style="border-color:color-mix(in oklab, var(--brand-700), black 22%)">
        <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
        <input type="hidden" name="action" value="create_recipe">
        <div class="form-row">
          <div class="col-md-4 mb-2">
            <label>เลือกเมนู</label>
            <select name="menu_id" class="form-control" required>
              <option value="">— เลือก —</option>
              <?php foreach ($menu_options as $m): ?>
                <option value="<?= (int)$m['menu_id'] ?>"><?= e($m['name']) ?> (ID: <?= (int)$m['menu_id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6 mb-2">
            <label>หัวสูตร / ชื่อสูตร</label>
            <input type="text" name="title" class="form-control" placeholder="เช่น มัทฉะลาเต้ (Iced Matcha Latte)" required>
          </div>
          <div class="col-md-2 mb-2 d-flex align-items-end">
            <button class="btn btn-psu btn-block"><i class="bi bi-save2"></i> บันทึก</button>
          </div>
        </div>
        <div class="text-muted small">* 1 เมนูอาจมีหลายชุดสูตรได้ ระบบจะใช้สูตรล่าสุดเป็นค่าเริ่มต้น</div>
      </form>
      <hr class="sep">
    </div>

    <?php if (empty($recipes)): ?>
      <div class="text-muted"><i class="bi bi-emoji-neutral"></i> ยังไม่มีสูตร หรือไม่พบตามคำค้น</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead>
            <tr>
              <th style="width:90px">ID</th>
              <th>เมนู</th>
              <th>หัวสูตร</th>
              <th class="text-right" style="width:200px">จัดการ</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recipes as $r): ?>
            <tr>
              <td class="code">#<?= (int)$r['recipe_id'] ?></td>
              <td><?= e($r['menu_name']) ?></td>
              <td><?= e($r['title']) ?></td>
              <td class="text-right">
                <a href="?edit=<?= (int)$r['recipe_id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-pencil-square"></i> แก้ไข</a>
                <form method="post" class="d-inline" onsubmit="return confirm('ลบสูตรนี้และข้อมูลย่อยทั้งหมด?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="delete_recipe">
                  <input type="hidden" name="recipe_id" value="<?= (int)$r['recipe_id'] ?>">
                  <button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> ลบ</button>
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
    <a href="recipes.php" class="btn btn-light mb-2"><i class="bi bi-arrow-left"></i> กลับรายการสูตร</a>
    <h5 class="label-blue mb-2"><i class="bi bi-journal-text"></i> แก้ไขสูตร: <span class="badge badge-psu">Recipe #<?= (int)$edit_header['recipe_id'] ?></span></h5>
    <div class="mb-2">เมนู: <strong><?= e($edit_header['menu_name']) ?></strong> <span class="text-muted">(menu_id <?= (int)$edit_header['menu_id'] ?>)</span></div>

    <!-- Edit recipe title -->
    <form method="post" class="border rounded p-3 mb-3" style="border-color:color-mix(in oklab, var(--brand-700), black 22%)">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="update_recipe_title">
      <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
      <div class="form-row">
        <div class="col-md-9 mb-2">
          <label>หัวสูตร / ชื่อสูตร</label>
          <input type="text" name="title" class="form-control" value="<?= e($edit_header['title'] ?? '') ?>" required>
        </div>
        <div class="col-md-3 mb-2 d-flex align-items-end">
          <button class="btn btn-psu btn-block"><i class="bi bi-save2"></i> บันทึกชื่อสูตร</button>
        </div>
      </div>
      <div class="text-muted small"><i class="bi bi-clock-history"></i> สร้างเมื่อ: <?= e($edit_header['created_at']) ?> • ปรับปรุงล่าสุด: <?= e($edit_header['updated_at']) ?></div>
    </form>

  <!-- Steps -->
<h6 class="label-blue"><i class="bi bi-list-ol"></i> ขั้นตอน (Steps)</h6>
<div class="table-responsive mb-2">
  <table class="table table-sm">
    <thead><tr><th>#</th><th>Step No.</th><th>คำอธิบาย</th><th class="text-right">จัดการ</th></tr></thead>
    <tbody>
      <?php if (empty($steps)): ?>
        <tr><td colspan="4" class="text-muted">ยังไม่มีขั้นตอน</td></tr>
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
          <td class="text-right">
              <button class="btn btn-primary btn-sm"><i class="bi bi-save2"></i> บันทึก</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('ลบขั้นตอนนี้?');">
              <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
              <input type="hidden" name="action" value="delete_step">
              <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
              <input type="hidden" name="step_id" value="<?= (int)$s['step_id'] ?>">
              <button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> ลบ</button>
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
    <div class="col-md-2 mb-2"><label>Step No.</label><input type="number" name="step_no" class="form-control" required></div>
    <div class="col-md-9 mb-2"><label>คำอธิบาย</label><input type="text" name="step_text" class="form-control" required></div>
    <div class="col-md-1 mb-2 d-flex align-items-end"><button class="btn btn-psu btn-block"><i class="bi bi-plus-circle"></i> เพิ่ม</button></div>
  </div>
</form>

<!-- Ingredients -->
<h6 class="label-blue"><i class="bi bi-basket3"></i> ส่วนผสม (Ingredients)</h6>
<div class="table-responsive mb-2">
  <table class="table table-sm">
    <thead><tr>
      <th>#</th><th>ผูก Step</th><th>ชื่อวัตถุดิบ</th><th>Qty</th><th>Unit</th><th>Note</th><th class="text-right">จัดการ</th>
    </tr></thead>
    <tbody>
      <?php if (empty($ings)): ?>
        <tr><td colspan="7" class="text-muted">ยังไม่มีส่วนผสม</td></tr>
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
          <td class="text-right">
            <button class="btn btn-primary btn-sm"><i class="bi bi-save2"></i> บันทึก</button>
            </form>
            <form method="post" class="d-inline" onsubmit="return confirm('ลบส่วนผสมนี้?');">
              <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
              <input type="hidden" name="action" value="delete_ing">
              <input type="hidden" name="ingredient_id" value="<?= (int)$i['ingredient_id'] ?>">
              <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
              <button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> ลบ</button>
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
      <label>ผูก Step</label>
      <select name="step_id" class="form-control">
        <option value="">(ทั่วไป)</option>
        <?php foreach ($steps as $s): ?>
          <option value="<?= (int)$s['step_id'] ?>">#<?= (int)$s['step_no'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 mb-2">
      <label>ชื่อวัตถุดิบ</label>
      <input type="text" name="name" class="form-control" required>
    </div>
    <div class="col-md-2 mb-2">
      <label>Qty</label>
      <input type="text" name="qty" class="form-control" placeholder="เช่น 30 / Fill-up">
    </div>
    <div class="col-md-2 mb-2">
      <label>Unit</label>
      <input type="text" name="unit" class="form-control" placeholder="ml / Teaspoon">
    </div>
    <div class="col-md-3 mb-2">
      <label>Note</label>
      <input type="text" name="note" class="form-control" placeholder="เช่น นมข้นหวาน / ใส่จนเต็มแก้ว">
    </div>
    <div class="col-12 mt-2">
      <button class="btn btn-psu"><i class="bi bi-plus-circle"></i> เพิ่ม</button>
    </div>
  </div>
</form>


    <!-- Ingredients -->
    <h6 class="label-blue"><i class="bi bi-basket3"></i> ส่วนผสม (Ingredients)</h6>
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
                <button class="btn btn-primary btn-sm"><i class="bi bi-save2"></i> บันทึก</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('ลบส่วนผสมนี้?');">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="action" value="delete_ing">
                  <input type="hidden" name="ingredient_id" value="<?= (int)$i['ingredient_id'] ?>">
                  <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
                  <button class="btn btn-danger btn-sm"><i class="bi bi-trash3"></i> ลบ</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Add ingredient -->
    <form method="post" class="border rounded p-3 mb-1" style="border-color:color-mix(in oklab, var(--brand-700), black 22%)">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="action" value="add_ing">
      <input type="hidden" name="recipe_id" value="<?= (int)$edit_header['recipe_id'] ?>">
      <div class="form-row">
        <div class="col-md-2 mb-2">
          <label>ผูก Step</label>
          <select name="step_id" class="form-control">
            <option value="">(ทั่วไป)</option>
            <?php foreach ($steps as $s): ?>
              <option value="<?= (int)$s['step_id'] ?>">#<?= (int)$s['step_no'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-2">
          <label>ชื่อวัตถุดิบ</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="col-md-2 mb-2">
          <label>Qty</label>
          <input type="text" name="qty" class="form-control" placeholder="เช่น 30 / Fill-up">
        </div>
        <div class="col-md-2 mb-2">
          <label>Unit</label>
          <input type="text" name="unit" class="form-control" placeholder="ml / Teaspoon">
        </div>
        <div class="col-md-2 mb-2">
          <label>Sort</label>
          <input type="number" name="sort_order" class="form-control" value="0">
        </div>
        <div class="col-md-1 mb-2 d-flex align-items-end">
          <button class="btn btn-psu btn-block"><i class="bi bi-plus-circle"></i> เพิ่ม</button>
        </div>
        <div class="col-12">
          <label>Note</label>
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

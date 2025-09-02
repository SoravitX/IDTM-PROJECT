<?php
// admin/users_list.php — รายชื่อผู้ใช้ทั้งหมด + ค้นหา/กรอง
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== รับตัวกรอง =====
$q    = trim((string)($_GET['q'] ?? ''));     // ค้นหา ชื่อ/username/student_ID
$role = trim((string)($_GET['role'] ?? ''));  // role เฉพาะ หรือว่าง = ทุก role

// ===== ดึงข้อมูล =====
$where  = '1=1';
$types  = '';
$params = [];

if ($q !== '') {
  $where .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.student_ID LIKE ?)";
  $types .= 'sss';
  $kw = '%'.$q.'%';
  $params[] = $kw; $params[] = $kw; $params[] = $kw;
}
if ($role !== '') {
  $where .= " AND u.role = ?";
  $types .= 's';
  $params[] = $role;
}

$sql = "
  SELECT u.user_id, u.username, u.student_ID, u.name, u.role
  FROM users u
  WHERE $where
  ORDER BY u.user_id DESC
";
if ($types !== '') {
  $stmt = $conn->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $users = $stmt->get_result();
  $stmt->close();
} else {
  $users = $conn->query($sql);
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>PSU Blue Cafe • รายชื่อผู้ใช้ทั้งหมด</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2; --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8; --ink:#0b2746;
}
body{margin:0; background:linear-gradient(135deg,#0D4071,#4173BD); color:#fff; font-family:"Segoe UI",Tahoma,Arial}
.container-xl{max-width:1400px}
.topbar{position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25); box-shadow:0 8px 20px rgba(0,0,0,.18)}
.brand{font-weight:900; letter-spacing:.3px}
.badge-user{ background:var(--psu-ocean-blue); color:#fff; font-weight:800; border-radius:999px }
.cardx{background:rgba(255,255,255,.95); color:var(--ink); border:1px solid #d9e6ff; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.22);}
.table thead th{ background:#f2f7ff; color:#083b6a; border-bottom:2px solid #e1ecff; }
.table td, .table th{ border-color:#e9f2ff !important; vertical-align: middle !important; }
.searchbox{background:#fff; border:2px solid var(--psu-ocean-blue); color:#000; border-radius:999px; padding:.4rem .9rem; min-width:260px}
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}
.topbar .btn-primary{background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800}
@media(max-width:576px){ .topbar{flex-wrap:wrap; gap:8px} }
</style>
</head>
<body>
<div class="container-xl py-3">

  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • ผู้ใช้ทั้งหมด</h4>

      <form class="form-inline" method="get">
        <input name="q" class="form-control form-control-sm searchbox mr-2"
               value="<?= h($q) ?>"
               type="search" placeholder="ค้นหา: ชื่อ / username / student_ID">
        <select name="role" class="form-control form-control-sm mr-2">
          <option value="">ทุกบทบาท</option>
          <?php
            $roles = ['admin','employee','kitchen','back','barista'];
            foreach($roles as $r){
              $sel = ($role===$r)?'selected':'';
              echo "<option value=\"".h($r)."\" $sel>".h($r)."</option>";
            }
          ?>
        </select>
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>

    <div class="d-flex align-items-center">
      <a href="add_user.php" class="btn btn-primary btn-sm mr-2">+ เพิ่มผู้ใช้</a>
      <a href="adminmenu.php" class="btn btn-light btn-sm mr-2">ไปหน้า Admin</a>
      <span class="badge badge-user px-3 py-2 mr-2">ผู้ดูแลระบบ</span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <div class="cardx p-3">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:80px">#</th>
            <th style="min-width:140px">Student ID</th>
            <th style="min-width:140px">Username</th>
            <th style="min-width:200px">ชื่อ-นามสกุล</th>
            <th style="min-width:120px">Role</th>
            <th style="min-width:120px" class="text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($users && $users->num_rows > 0): ?>
          <?php while($u = $users->fetch_assoc()): ?>
            <tr>
              <td><?= (int)$u['user_id'] ?></td>
              <td><?= h($u['student_ID'] ?? '') ?></td>
              <td><?= h($u['username']) ?></td>
              <td><?= h($u['name']) ?></td>
              <td><span class="badge badge-info p-2"><?= h($u['role'] ?: '-') ?></span></td>
              <td class="text-right">
                <a class="btn btn-outline-primary btn-sm" href="edit_user.php?id=<?= (int)$u['user_id'] ?>">
                  <i class="bi bi-pencil-square"></i> แก้ไข
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted">ไม่พบผู้ใช้</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>

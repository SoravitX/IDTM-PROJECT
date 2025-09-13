<?php
// admin/users_list.php — รายชื่อผู้ใช้ทั้งหมด + ค้นหา/กรอง + รวมชั่วโมง (ตกแต่ง + เรียงตาม ชื่อ→SID→Username)
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtHM(int $sec): string {
  if ($sec <= 0) return '0:00 ชม.';
  $h = intdiv($sec, 3600);
  $m = intdiv($sec % 3600, 60);
  return $h . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ' ชม.';
}

// ===== รับตัวกรอง =====
$q    = trim((string)($_GET['q'] ?? ''));     // ค้นหา ชื่อ/username/student_ID
$role = trim((string)($_GET['role'] ?? ''));  // เลือกบทบาท

// ===== เงื่อนไขค้นหา =====
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

// ===== ดึงผู้ใช้ + รวมชั่วโมงจาก attendance (เฉพาะบันทึกที่ปิดงานแล้ว) =====
$sql = "
  SELECT
    u.user_id, u.username, u.student_ID, u.name, u.role, u.status,
    COALESCE(SUM(CASE
      WHEN a.time_out <> '00:00:00' AND a.hour_type = 'fund'
      THEN TIMESTAMPDIFF(SECOND, CONCAT(a.date_in,' ',a.time_in), CONCAT(a.date_out,' ',a.time_out))
      ELSE 0 END
    ),0) AS sec_fund,
    COALESCE(SUM(CASE
      WHEN a.time_out <> '00:00:00' AND a.hour_type = 'normal'
      THEN TIMESTAMPDIFF(SECOND, CONCAT(a.date_in,' ',a.time_in), CONCAT(a.date_out,' ',a.time_out))
      ELSE 0 END
    ),0) AS sec_normal
  FROM users u
  LEFT JOIN attendance a ON a.user_id = u.user_id
  WHERE $where
  GROUP BY u.user_id, u.username, u.student_ID, u.name, u.role, u.status
  /* ✅ เรียงลำดับตามที่ต้องการ: ชื่อ-นามสกุล → Student ID → Username */
  ORDER BY u.name ASC, u.student_ID ASC, u.username ASC
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
  --psu-deep:#0D4071; --psu-ocean:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2;  --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --ink:#0b2746; --soft:#f7fbff;
}
body{margin:0; background:linear-gradient(135deg,var(--psu-deep),var(--psu-ocean)); color:#fff; font-family:"Segoe UI",Tahoma,Arial}
.container-xl{max-width:1400px}

/* Topbar */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(13,64,113,.92); border:1px solid rgba(187,180,216,.25);
  box-shadow:0 8px 20px rgba(0,0,0,.18)
}
.brand{font-weight:900; letter-spacing:.3px}
.badge-user{ background:#3b7ddd; color:#fff; font-weight:800; border-radius:999px }
.searchbox{background:#fff; border:2px solid var(--psu-ocean); color:#000; border-radius:999px; padding:.4rem .9rem; min-width:260px}
.topbar .btn-primary{background:linear-gradient(180deg,#3aa3ff,#1f7ee8); border-color:#1669c9; font-weight:800}
.btn-ghost{background:var(--psu-andaman); border:1px solid #063d63; color:#fff; font-weight:700}

/* Card & Table */
.cardx{
  background:rgba(255,255,255,.97); color:var(--ink);
  border:1px solid #d9e6ff; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,.22);
}
.table thead th{
  background:#f2f7ff; color:#083b6a; border-bottom:2px solid #e1ecff; font-weight:800;
}
.table td, .table th{ border-color:#e9f2ff !important; vertical-align: middle !important; }
.table tbody tr:nth-child(odd){ background: #fcfdff; }
.table tbody tr:hover{ background:#f5faff; }

/* Badges */
.badge-role{padding:.35rem .6rem; border-radius:999px; font-weight:800; background:#eaf4ff; color:#0D4071; border:1px solid #cfe2ff}
.badge-status{padding:.35rem .6rem; border-radius:999px; font-weight:800}
.badge-status-fund{background:#eaf7ea; color:#1b5e20; border:1px solid #cfe9cf}
.badge-status-norm{background:#e9f5ff; color:#0D4071; border:1px solid #cfe2ff}

/* Small helper row above table */
.legend{
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
  color:#0D4071; background:#edf5ff; border:1px solid #d8e9ff;
  border-radius:12px; padding:8px 12px; font-weight:700
}
.dot{width:10px; height:10px; border-radius:50%}
.dot-fund{background:#1b5e20} .dot-norm{background:#0D4071}
</style>
</head>
<body>
<div class="container-xl py-3">

  <!-- Topbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • ผู้ใช้ทั้งหมด</h4>
      <form class="form-inline" method="get">
        <input name="q" class="form-control form-control-sm searchbox mr-2"
               value="<?= h($q) ?>" type="search"
               placeholder="ค้นหา: ชื่อ / username / student_ID">
        <select name="role" class="form-control form-control-sm mr-2">
          <option value="">ทุกบทบาท</option>
          <?php
            // ถ้าระบบใช้จริงเหลือแค่ admin / employee ให้ใช้ 2 ตัวนี้ได้เลย
            $roles = ['admin','employee'];
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

  <!-- Legend -->
  <div class="cardx p-2 mb-2">
    <div class="legend">
      <span>สถานะผู้ใช้:</span>
      <span class="dot dot-fund"></span> ชั่วโมงทุน
      <span class="dot dot-norm ml-2"></span> ชั่วโมงปกติ
      <span class="ml-3 text-muted" style="font-weight:600">*เวลารวมคิดเฉพาะรายการที่ปิดงานแล้ว</span>
    </div>
  </div>

  <!-- Table -->
  <div class="cardx p-3">
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <!-- ✅ เรียงหัวคอลัมน์ตามที่ต้องการ: ชื่อ → Student ID → Username -->
            <th style="min-width:200px">ชื่อ-นามสกุล</th>
            <th style="min-width:130px">Student ID</th>
            <th style="min-width:130px">Username</th>
            <th style="min-width:110px">Role</th>
            <th style="min-width:120px">Status</th>
            <th class="text-right" style="min-width:130px">ชั่วโมงทุน</th>
            <th class="text-right" style="min-width:130px">ชั่วโมงปกติ</th>
            <th class="text-right" style="min-width:130px">รวมทั้งหมด</th>
            <th style="min-width:160px" class="text-right">จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($users && $users->num_rows > 0): ?>
          <?php while($u = $users->fetch_assoc()):
            $sec_fund   = (int)($u['sec_fund'] ?? 0);
            $sec_normal = (int)($u['sec_normal'] ?? 0);
            $sec_total  = $sec_fund + $sec_normal;
          ?>
            <tr>
              <td><?= (int)$u['user_id'] ?></td>
              <td><?= h($u['name']) ?></td>
              <td><?= h($u['student_ID'] ?? '') ?></td>
              <td><?= h($u['username']) ?></td>
              <td><span class="badge-role"><?= h($u['role'] ?: '-') ?></span></td>
              <td>
                <?php if($u['status']==='ชั่วโมงทุน'): ?>
                  <span class="badge-status badge-status-fund">ชั่วโมงทุน</span>
                <?php else: ?>
                  <span class="badge-status badge-status-norm">ชั่วโมงปกติ</span>
                <?php endif; ?>
              </td>
              <td class="text-right"><?= fmtHM($sec_fund) ?></td>
              <td class="text-right"><?= fmtHM($sec_normal) ?></td>
              <td class="text-right"><strong><?= fmtHM($sec_total) ?></strong></td>
              <td class="text-right">
                <a class="btn btn-outline-primary btn-sm" href="edit_user.php?id=<?= (int)$u['user_id'] ?>">
                  <i class="bi bi-pencil-square"></i> แก้ไข
                </a>
                <a class="btn btn-outline-info btn-sm" href="user_detail.php?id=<?= (int)$u['user_id'] ?>">
                  รายละเอียดชั่วโมง
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="10" class="text-center text-muted">ไม่พบผู้ใช้</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>

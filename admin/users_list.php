
<?php
// admin/users_list.php — รายชื่อผู้ใช้ทั้งหมด + ค้นหา/กรอง + รวมชั่วโมง
// (ตกแต่ง UI โทน Dark Cyan • ไม่เปลี่ยน logic/SQL)

// admin/users_list.php — รายชื่อผู้ใช้ทั้งหมด + ค้นหา/กรอง + รวมชั่วโมง
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
html,body{height:100%}
body{
  margin:0;
  background:
    radial-gradient(900px 340px at 105% -10%, rgba(39,200,207,.14), transparent 65%),
    linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));
  color:var(--text-strong);
  font-family:"Segoe UI",Tahoma,Arial,sans-serif;
}
.container-xl{max-width:1400px}

/* Topbar */
.topbar{
  position:sticky; top:0; z-index:50; padding:12px 16px; border-radius:14px;
  background:rgba(35,42,49,.85); border:1px solid #2a323a; backdrop-filter: blur(6px);
  box-shadow:var(--shadow);
}
.brand{font-weight:900; letter-spacing:.3px; color:var(--brand-900)}
.badge-user{
  background:linear-gradient(180deg,#2f3640,#3a424c);
  color:var(--brand-900); font-weight:800; border-radius:999px; border:1px solid #3f4853
}
.searchbox{
  background:#101418; border:2px solid #2c353d; color:var(--text-normal);
  border-radius:999px; padding:.45rem .9rem; min-width:260px
}
.searchbox::placeholder{ color:#7d8b97 }
.searchbox:focus{ box-shadow:0 0 0 .2rem rgba(0,173,181,.25); border-color:var(--brand-500); color:var(--text-strong) }

.btn-ghost{
  background:linear-gradient(180deg,#00ADB5,#089aa1);
  border:1px solid #078b91; color:#001316; font-weight:800; border-radius:12px;
}
.topbar .btn-primary{
  background:linear-gradient(180deg,#00ADB5,#078f96);
  border-color:#067a80; font-weight:800; color:#001317;
}
.topbar .btn-light{
  background:#2a3139; color:#dfe6eb; border:1px solid #3a444f; font-weight:800;
}
.btn-outline-light{ color:#cfe3e6; border-color:#44525e }
.btn-outline-light:hover{ background:#2a333b; color:#fff }

/* Card & Table */
.cardx{
  background:linear-gradient(180deg,var(--surface),var(--surface-2));
  color:var(--ink); border:1px solid #2b353f; border-radius:16px; box-shadow:var(--shadow);
}
.table-wrap{ max-height: 68vh; overflow:auto; border-radius:12px }

/* table header sticky + dark */
.table thead th{
  position: sticky; top: 0; z-index: 1;
  background:linear-gradient(180deg,var(--surface-3),#252c33);
  color:var(--brand-900);
  border-bottom:2px solid #3a4652;
  font-weight:800;
}
.table{
  color:var(--text-strong);
}
.table td, .table th{
  border-color:#2f3a44 !important; vertical-align: middle !important;
}
.table tbody tr:nth-child(odd){ background: #1E252B; }
.table tbody tr:nth-child(even){ background: #1b2127; }
.table tbody tr:hover td{ background:#232b32; }

/* Badges */
.badge-role{
  padding:.35rem .6rem; border-radius:999px; font-weight:800;
  background:#0a1116; color:#9ee7eb; border:1px solid #21414a
}
.badge-status{padding:.35rem .6rem; border-radius:999px; font-weight:800}
.badge-status-fund{
  background:#0f1a10; color:#62e09b; border:1px solid #204b2f
}
.badge-status-norm{
  background:#0c1417; color:#6ee7f0; border:1px solid #1f3e45
}

/* Helper row */
.legend{
  display:flex; align-items:center; gap:10px; flex-wrap:wrap;
  color:var(--brand-900);
  background:linear-gradient(180deg,#11171b,#151c21);
  border:1px solid #28333b; border-radius:12px; padding:8px 12px; font-weight:700
}
.legend .text-muted{ color:var(--text-muted) !important }
.dot{width:10px; height:10px; border-radius:50%}
.dot-fund{background:#2ecc71} .dot-norm{background:#00ADB5}

/* Small utilities */
.kpi-chip{
  display:inline-flex; align-items:center; gap:6px;
  background:#0e1519; color:#bfeff2; border:1px solid #244b52;
  border-radius:999px; padding:6px 10px; font-weight:800
}

/* Avatar circle */
.avatar{
  width:28px;height:28px;border-radius:50%;
  background:#0e1519;color:#8adfe4;display:flex;align-items:center;justify-content:center;font-weight:900;
  border:1px solid #244b52
}

/* Focus ring */
:focus-visible{ outline:3px solid rgba(0,173,181,.45); outline-offset:3px; border-radius:10px }

/* Scrollbar */
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2a323a;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#33404a}
*::-webkit-scrollbar-track{background:#11161a}
</style>
</head>
<body>
<div class="container-xl py-3">

  <!-- Topbar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3"><i class="bi bi-people"></i> PSU Blue Cafe • ผู้ใช้ทั้งหมด</h4>
      <form class="form-inline" method="get">
        <input name="q" class="form-control form-control-sm searchbox mr-2"
               value="<?= h($q) ?>" type="search"
               placeholder="ค้นหา: ชื่อ / username / student_ID" aria-label="ค้นหา">
        <select name="role" class="form-control form-control-sm mr-2" style="background:#0e1317;color:#cfe3e6;border:1px solid #2b343c">
          <option value="">ทุกบทบาท</option>
          <?php
            $roles = ['admin','employee'];
            foreach($roles as $r){
              $sel = ($role===$r)?'selected':'';
              echo "<option value=\"".h($r)."\" $sel>".h($r)."</option>";
            }
          ?>
        </select>
        <button class="btn btn-sm btn-ghost"><i class="bi bi-search"></i> ค้นหา</button>
      </form>
    </div>
    <div class="d-flex align-items-center">
      <a href="add_user.php" class="btn btn-primary btn-sm mr-2"><i class="bi bi-person-plus"></i> เพิ่มผู้ใช้</a>
      <a href="adminmenu.php" class="btn btn-light btn-sm mr-2"><i class="bi bi-gear"></i> ไปหน้า Admin</a>
      <span class="badge badge-user px-3 py-2 mr-2"><i class="bi bi-shield-lock"></i> ผู้ดูแลระบบ</span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php"><i class="bi bi-box-arrow-right"></i> ออกจากระบบ</a>
    </div>
  </div>

  <!-- Legend + Quick KPI -->
  <div class="cardx p-2 mb-2">
    <div class="d-flex align-items-center justify-content-between flex-wrap">
      <div class="legend mb-2 mb-sm-0">
        <span>สถานะเวลา:</span>
        <span class="dot dot-fund"></span> ชั่วโมงทุน
        <span class="dot dot-norm ml-2"></span> ชั่วโมงปกติ
        <span class="ml-3 text-muted" style="font-weight:600">*รวมเฉพาะรายการที่ปิดงานแล้ว</span>
      </div>
      <div class="kpi-chip">
        <i class="bi bi-people"></i>
        รวมผู้ใช้ที่ตรงเงื่อนไข:
        <strong class="ml-1">
          <?= isset($users) && $users instanceof mysqli_result ? (int)$users->num_rows : 0 ?>
        </strong>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="cardx p-3">
    <div class="table-wrap">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <th style="min-width:220px">ชื่อ-นามสกุล</th>
            <th style="min-width:130px">Student ID</th>
            <th style="min-width:130px">Username</th>
            <th style="min-width:110px">Role</th>
            <th style="min-width:120px">Status</th>
            <th class="text-right" style="min-width:130px">ชั่วโมงทุน</th>
            <th class="text-right" style="min-width:130px">ชั่วโมงปกติ</th>
            <th class="text-right" style="min-width:130px">รวมทั้งหมด</th>
            <th style="min-width:180px" class="text-right">จัดการ</th>
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
              <td>
                <div class="d-flex align-items-center">
                  <div class="mr-2 avatar">
                    <?= strtoupper(mb_substr(trim($u['name']!==''?$u['name']:$u['username']),0,1,'UTF-8')) ?>
                  </div>
                  <div><?= h($u['name']) ?></div>
                </div>
              </td>
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
                <a class="btn btn-outline-light btn-sm" style="border-color:#3a4652" href="edit_user.php?id=<?= (int)$u['user_id'] ?>">
                  <i class="bi bi-pencil-square"></i> แก้ไข
                </a>
                <a class="btn btn-outline-light btn-sm" style="border-color:#3a4652" href="user_detail.php?id=<?= (int)$u['user_id'] ?>">
                  <i class="bi bi-clock-history"></i> ชั่วโมง
                </a>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="10" class="text-center" style="color:var(--text-muted)">ไม่พบผู้ใช้</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- UX: โฟกัสช่องค้นหาเร็วด้วยปุ่ม / -->
<script>
document.addEventListener('keydown', e=>{
  if(e.key === '/'){
    const q = document.querySelector('input[name="q"]');
    if(q){ e.preventDefault(); q.focus(); q.select(); }
  }
});
</script>
</body>
</html>
```

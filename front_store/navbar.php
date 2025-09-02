<?php
// navbar.php — PSU Blue Cafe Navbar ใช้ include ได้ทุกหน้า
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="topbar d-flex align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center">
    <h4 class="brand mb-0 mr-3">PSU Blue Cafe</h4>

    <form class="form-inline" method="get" action="front_store.php">
      <input
        name="q"
        value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        class="form-control form-control-sm searchbox mr-2"
        type="search"
        placeholder="ค้นหารายการ (กด / เพื่อค้นหา)">
      <?php if (!empty($_GET['category_id'])): ?>
        <input type="hidden" name="category_id" value="<?= (int)$_GET['category_id'] ?>">
      <?php endif; ?>
      <button class="btn btn-sm btn-ghost">ค้นหา</button>
    </form>
  </div>

  <!-- actions (right) -->
  <div class="d-flex align-items-center topbar-actions">
    <a href="checkout.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">
      ไปหน้า Check Out
    </a>
    <span class="badge badge-user px-3 py-2 mr-2">
      ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? 'guest', ENT_QUOTES, 'UTF-8') ?>
    </span>
    <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
  </div>
</div>

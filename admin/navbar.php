<!-- navbar.php -->
<nav style="background-color:#A67B5B; padding:10px 20px; display:flex; justify-content: space-between; align-items: center; color: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div class="nav-left" style="font-weight: 700; font-size: 1.5rem;">
        🍽️ ระบบจัดการร้านอาหาร (Admin)
    </div>
    <div class="nav-center" style="display: flex; gap: 15px;">
        <a href="adminmenu.php" style="color: white; text-decoration: none; font-weight: 600;">จัดการเมนู</a>
        <a href="admincategories.php" style="color: white; text-decoration: none; font-weight: 600;">จัดการหมวดหมู่</a>
        <a href="orders.php" style="color: white; text-decoration: none; font-weight: 600;">รายการสั่งซื้อ</a>
        <a href="reports.php" style="color: white; text-decoration: none; font-weight: 600;">รายงาน</a>
        <a href="settings.php" style="color: white; text-decoration: none; font-weight: 600;">ตั้งค่า</a>
    </div>
    <div class="nav-right" style="font-weight: 600;">
        <?php 
        // แสดงชื่อผู้ใช้งาน จาก session ถ้ามี
        session_start();
        echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'admin_user'; 
        ?>
        &nbsp;|&nbsp;
        <a href="../logout.php" style="color: #FED8B1; text-decoration: none; font-weight: 700;">ออกจากระบบ</a>
    </div>
</nav>

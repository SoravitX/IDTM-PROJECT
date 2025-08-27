<?php
// logout.php
session_start();

// ลบค่าทั้งหมดใน session
$_SESSION = [];

// ถ้ามี cookie ของ session ให้เคลียร์ด้วย
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// redirect กลับไปหน้า login
header("Location: index.php?msg=loggedout");
exit;

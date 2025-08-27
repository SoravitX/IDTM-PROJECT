<?php
$host = "localhost"; 
$user = "root";       // ชื่อผู้ใช้ MySQL
$pass = "";           // รหัสผ่าน MySQL
$dbname = "psu_blue_cafe";  // ชื่อฐานข้อมูล

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}
?>

<?php
// toggle_sale.php — toggle menu is_active
declare(strict_types=1);
session_start();
require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$to = isset($_POST['to']) ? (int)$_POST['to'] : 1;
$to = $to === 1 ? 1 : 0;

if ($id > 0) {
  $stmt = $conn->prepare("UPDATE menu SET is_active=? WHERE menu_id=?");
  $stmt->bind_param("ii", $to, $id);
  $stmt->execute();
  $stmt->close();
  header("Location: adminmenu.php?msg=" . ($to ? "toggled_on" : "toggled_off"));
  exit;
}
header("Location: adminmenu.php");

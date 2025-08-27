<?php
// api/orders_feed.php
declare(strict_types=1);
session_start(); // ⬅️ เพิ่ม session เพื่อรู้ว่าใครคือผู้ใช้ฝั่งหน้าร้าน

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$since = isset($_GET['since']) ? trim($_GET['since']) : '';
$mine  = isset($_GET['mine']) && $_GET['mine'] == '1';  // ⬅️ เปิดโหมดกรองเฉพาะของผู้ใช้
$uid   = (int)($_SESSION['uid'] ?? 0);

$orders = [];

if ($since !== '') {
  if ($mine && $uid > 0) {
    // ✅ อัปเดตเฉพาะออเดอร์ของผู้ใช้คนนั้น
    $stmt = $conn->prepare("
      SELECT order_id, status, updated_at
      FROM orders
      WHERE updated_at > ? AND user_id = ?
      ORDER BY updated_at ASC, order_id ASC
    ");
    $stmt->bind_param('si', $since, $uid);
  } else {
    // กรณีทั่วไป (ถ้าอยากให้เฉพาะของหน้าร้านใช้ mine=1 เสมอ)
    $stmt = $conn->prepare("
      SELECT order_id, status, updated_at
      FROM orders
      WHERE updated_at > ?
      ORDER BY updated_at ASC, order_id ASC
    ");
    $stmt->bind_param('s', $since);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) {
    $orders[] = [
      'order_id'   => (int)$r['order_id'],
      'status'     => (string)$r['status'],
      'updated_at' => (string)$r['updated_at'],
    ];
  }
  $stmt->close();
}

echo json_encode([
  'ok'     => true,
  'now'    => date('Y-m-d H:i:s'),
  'orders' => $orders
], JSON_UNESCAPED_UNICODE);

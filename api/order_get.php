<?php
// api/order_get.php — get 1 order with details
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require __DIR__.'/../db.php';
$conn->set_charset('utf8mb4');

$oid = (int)($_GET['id'] ?? 0);
if ($oid <= 0) {
  echo json_encode(['ok'=>false,'error'=>'missing id']); exit;
}

// header
$h = null;
$stmt = $conn->prepare("
  SELECT o.order_id,o.user_id,o.order_time,o.status,o.total_price,
         COALESCE(u.username,'user') AS username
  FROM orders o
  LEFT JOIN users u ON u.user_id=o.user_id
  WHERE o.order_id=?
  LIMIT 1
");
$stmt->bind_param('i', $oid);
$stmt->execute();
$res = $stmt->get_result();
$h = $res->fetch_assoc();
$stmt->close();

if (!$h) { echo json_encode(['ok'=>false,'error'=>'not found']); exit; }

// lines
$lines = [];
$stmt = $conn->prepare("
  SELECT d.order_detail_id,d.menu_id,d.quantity,d.note,d.total_price,
         m.name AS menu_name
  FROM order_details d
  JOIN menu m ON m.menu_id=d.menu_id
  WHERE d.order_id=?
  ORDER BY d.order_detail_id
");
$stmt->bind_param('i', $oid);
$stmt->execute();
$res = $stmt->get_result();
while($r = $res->fetch_assoc()) $lines[] = $r;
$stmt->close();

echo json_encode(['ok'=>true,'order'=>$h,'lines'=>$lines], JSON_UNESCAPED_UNICODE);

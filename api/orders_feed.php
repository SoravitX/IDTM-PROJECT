<?php
// api/orders_feed.php
declare(strict_types=1);
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require __DIR__ . '/../db.php';
$conn->set_charset('utf8mb4');

$mine  = isset($_GET['mine']) && $_GET['mine'] == '1';
$uid   = (int)($_SESSION['uid'] ?? 0);

/* ถ้าไม่ส่ง since มา ให้ตั้งเป็นเวลาปัจจุบันทันที เพื่อให้ครั้งแรกเป็น “baseline”
   แล้วรอบถัดไปจะดึงเฉพาะสิ่งที่เกิดหลัง baseline นี้ */
$since = trim((string)($_GET['since'] ?? ''));
if ($since === '') {
  $since = date('Y-m-d H:i:s');
}

$orders = [];
$slips  = [];

/* ------- Orders ที่เปลี่ยนแปลงหลัง $since (รวม slip_count เสมอ) ------- */
if ($mine && $uid > 0) {
  $sql = "
    SELECT o.order_id, o.status, o.updated_at,
           (SELECT COUNT(*) FROM payment_slips ps WHERE ps.order_id = o.order_id) AS slip_count
    FROM orders o
    WHERE o.updated_at > ? AND o.user_id = ?
    ORDER BY o.updated_at ASC, o.order_id ASC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('si', $since, $uid);
} else {
  $sql = "
    SELECT o.order_id, o.status, o.updated_at,
           (SELECT COUNT(*) FROM payment_slips ps WHERE ps.order_id = o.order_id) AS slip_count
    FROM orders o
    WHERE o.updated_at > ?
    ORDER BY o.updated_at ASC, o.order_id ASC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $since);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $orders[] = [
    'order_id'   => (int)$r['order_id'],
    'status'     => (string)$r['status'],
    'updated_at' => (string)$r['updated_at'],
    'slip_count' => (int)$r['slip_count'],
  ];
}
$stmt->close();

/* ------- Slips ที่อัปโหลดหลัง $since (ให้หลังร้านอัปเดตตัวเลขได้ทันที) ------- */
if ($mine && $uid > 0) {
  $sql = "
    SELECT ps.id, ps.order_id, ps.user_id, ps.file_path, ps.mime, ps.size_bytes, ps.uploaded_at, ps.note
    FROM payment_slips ps
    INNER JOIN orders o ON o.order_id = ps.order_id
    WHERE ps.uploaded_at > ? AND o.user_id = ?
    ORDER BY ps.uploaded_at ASC, ps.id ASC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('si', $since, $uid);
} else {
  $sql = "
    SELECT id, order_id, user_id, file_path, mime, size_bytes, uploaded_at, note
    FROM payment_slips
    WHERE uploaded_at > ?
    ORDER BY uploaded_at ASC, id ASC
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $since);
}
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $slips[] = [
    'id'          => (int)$r['id'],
    'order_id'    => (int)$r['order_id'],
    'user_id'     => (int)$r['user_id'],
    'file_path'   => (string)$r['file_path'],
    'mime'        => (string)$r['mime'],
    'size_bytes'  => (int)$r['size_bytes'],
    'uploaded_at' => (string)$r['uploaded_at'],
    'note'        => (string)($r['note'] ?? ''),
  ];
}
$stmt->close();

echo json_encode([
  'ok'     => true,
  'now'    => date('Y-m-d H:i:s'),
  'orders' => $orders,
  'slips'  => $slips
], JSON_UNESCAPED_UNICODE);

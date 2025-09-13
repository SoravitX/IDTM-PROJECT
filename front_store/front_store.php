<?php
// front_store/front_store.php — POS UI + modal popup + Voice Ready Notification + แสดงโปรโมชันต่อเมนู
// iPad/แท็บเล็ต: 3 คอลัมน์, เดสก์ท็อป: 5 คอลัมน์
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // ✅ เปิด error report ช่วยจับ SQL พัง
$conn->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function money_fmt($n){ return number_format((float)$n, 2); }
function cart_key(int $menu_id, string $note): string { return $menu_id.'::'.md5(trim($note)); }
function safe_key(string $k): string { return htmlspecialchars($k, ENT_QUOTES, 'UTF-8'); }

/**
 * ดึงราคารวมท็อปปิงและชื่อ จากตาราง toppings เท่านั้น
 * return ['extra' => float, 'names' => string[]]
 */
function toppings_info(mysqli $conn, int $menu_id, array $picked_ids): array {
  $picked_ids = array_values(array_unique(array_map('intval', $picked_ids)));
  if (!$picked_ids) return ['extra'=>0.0, 'names'=>[]];

  $in = implode(',', array_fill(0, count($picked_ids), '?'));
  $types = str_repeat('i', count($picked_ids));

  $sql = "SELECT topping_id, name, base_price AS price
          FROM toppings
          WHERE is_active = 1 AND topping_id IN ($in)";
  $st = $conn->prepare($sql);
  $st->bind_param($types, ...$picked_ids);
  $st->execute();
  $rs = $st->get_result();

  $extra = 0.0; $names = [];
  while ($r = $rs->fetch_assoc()) {
    $extra += (float)$r['price'];
    $names[] = (string)$r['name'];
  }
  $st->close();
  return ['extra'=>$extra, 'names'=>$names];
}

/**
 * คืนโปรโมชัน (แบบ ITEM) ที่ลดเป็น "จำนวนเงิน" ได้มากสุดสำหรับเมนูนี้ ณ ขณะนี้
 * return: ['promo_id'=>int,'name'=>string,'type'=>PERCENT|FIXED,'value'=>float,'amount'=>float] หรือ null
 * หมายเหตุ: amount = เงินที่ลดได้ต่อ 1 หน่วย (คำนวณจากราคา "เมนูฐาน" ไม่รวมท็อปปิง)
 */
function best_item_promo(mysqli $conn, int $menu_id, float $base_price): ?array {
  $sql = "
    SELECT p.promo_id, p.name, p.discount_type, p.discount_value, p.max_discount
    FROM promotion_items pi
    JOIN promotions p ON p.promo_id = pi.promo_id
    WHERE pi.menu_id = ?
      AND p.scope='ITEM'
      AND p.is_active = 1
      AND NOW() BETWEEN p.start_at AND p.end_at
    ORDER BY LEAST(
      CASE WHEN p.discount_type='PERCENT'
           THEN (p.discount_value/100.0)*?
           ELSE p.discount_value
      END,
      COALESCE(p.max_discount, 999999999)
    ) DESC
    LIMIT 1
  ";
  $st = $conn->prepare($sql);
  $st->bind_param('id', $menu_id, $base_price); // i = menu_id, d = base_price
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  $st->close();

  if (!$r) return null;

  $raw = ($r['discount_type']==='PERCENT')
          ? ($r['discount_value']/100.0)*$base_price
          : (float)$r['discount_value'];

  $amount = min($raw, (float)($r['max_discount'] ?? 999999999));
  if ($amount <= 0) return null;

  return [
    'promo_id' => (int)$r['promo_id'],
    'name'     => (string)$r['name'],
    'type'     => (string)$r['discount_type'],
    'value'    => (float)$r['discount_value'],
    'amount'   => (float)$amount, // ลดได้ต่อ 1 แก้ว/หน่วย
  ];
}

/* ---------- ฟังก์ชันเรนเดอร์ตะกร้า (ใช้ทั้งหน้า + AJAX) ---------- */
function render_cart_box(): string {
  ob_start();
  ?>
  <div class="pos-card cart">
    <div class="d-flex align-items-center justify-content-between p-3 pt-3 pb-0">
      <div class="h5 mb-0 font-weight-bold">ออเดอร์</div>
      <a class="btn btn-sm btn-outline-light" href="front_store.php?action=clear"
         onclick="return confirm('ล้างออเดอร์ทั้งหมด?');">ล้าง</a>
    </div>
    <hr class="my-2" style="border-color:rgba(255,255,255,.25)">
    <div class="p-2 pt-0">
    <?php if(!empty($_SESSION['cart'])): ?>
      <form method="post" id="frmCart">
        <input type="hidden" name="action" value="update">
        <div class="table-responsive">
          <table class="table table-sm table-cart">
            <thead>
              <tr>
                <th>รายการ</th>
                <th class="text-right">ราคา</th>
                <th class="text-center" style="width:86px;">จำนวน</th>
                <th class="text-right">รวม</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php
              $gross_total = 0.0;      // รวมหลังหักโปรแล้ว (เพราะบันทึกราคา/หน่วยแบบหักแล้ว)
              $discount_total = 0.0;   // ใช้สำหรับ "แสดงว่าประหยัดไปเท่าไหร่" เท่านั้น

              foreach($_SESSION['cart'] as $key=>$it):
                $qty = (int)($it['qty'] ?? 0);
                $unit_price = (float)($it['price'] ?? 0.0); // เป็นราคาหลังหักโปรแล้ว
                $line = $unit_price * $qty;
                $gross_total += $line;

                // ดึงข้อมูลโปรจาก session (ถ้ามี)
                $promo_name = (string)($it['promo_name'] ?? '');
                $unit_discount = (float)($it['unit_discount'] ?? 0.0);
                $line_discount = $unit_discount * $qty;
                if ($unit_discount > 0) { $discount_total += $line_discount; }
            ?>
              <tr>
                <td class="align-middle">
                  <div class="font-weight-bold" style="color:#0D4071">
                    <?= htmlspecialchars($it['name'],ENT_QUOTES,'UTF-8') ?>
                  </div>

                  <?php if (!empty($it['note'])): ?>
                    <?php $parts = array_filter(array_map('trim', explode('|', $it['note']))); ?>
                    <div class="note-list">
                      <?php foreach ($parts as $p):
                        $k=''; $v=$p; if (strpos($p, ':')!==false) { [$k,$v] = array_map('trim', explode(':',$p,2)); } ?>
                        <span class="note-pill">
                          <?php if ($k!==''): ?><span class="k"><?= htmlspecialchars($k,ENT_QUOTES,'UTF-8') ?>:</span><?php endif; ?>
                          <span class="v"><?= htmlspecialchars($v,ENT_QUOTES,'UTF-8') ?></span>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($promo_name !== '' && $unit_discount > 0): ?>
                    <div class="mt-1">
                      <span class="note-pill" title="ส่วนลดโปรโมชันถูกหักแล้วในราคาต่อหน่วย">
                        โปรฯ: <?= htmlspecialchars($promo_name,ENT_QUOTES,'UTF-8') ?>
                        — ลด <?= number_format($unit_discount,2) ?> ฿/ชิ้น
                        × <?= $qty ?> = <strong>-<?= number_format($line_discount,2) ?> ฿</strong>
                      </span>
                    </div>
                  <?php endif; ?>
                </td>

                <td class="text-right align-middle"><?= number_format($unit_price,2) ?></td>
                <td class="text-center align-middle">
                  <input class="form-control form-control-sm" type="number"
                         name="qty[<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>]"
                         value="<?= $qty ?>" min="0">
                </td>
                <td class="text-right align-middle"><?= number_format($line,2) ?></td>
                <td class="text-right align-middle">
                  <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-primary js-edit"
                       data-menu-id="<?= (int)$it['menu_id'] ?>"
                       data-key="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>"
                       href="menu_detail.php?id=<?= (int)$it['menu_id'] ?>&edit=1&key=<?= urlencode($key) ?>"
                       title="แก้ไข">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <a class="btn btn-outline-danger" title="ลบ"
                       href="front_store.php?action=remove&key=<?= urlencode($key) ?>"
                       onclick="return confirm('ลบรายการนี้?');">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>

      <?php
        $gross_total = $gross_total ?? 0.0;
        $discount_total = $discount_total ?? 0.0;
        // ราคาจริงที่ต้องจ่าย = gross_total (เพราะ unit_price ถูกหักโปรแล้ว)
        $net_total = $gross_total;
      ?>
      </div>

      <!-- สรุปยอด -->
      <div class="p-3 pt-0" style="background:#fff;border-top:1px solid #e7eefc;border-bottom-left-radius:14px;border-bottom-right-radius:14px">
        <div class="d-flex justify-content-between">
          <div class="font-weight-bold" style="color:#0D4071">ยอดรวมหลังหักโปร</div>
          <div class="font-weight-bold" style="color:#0D4071"><?= number_format($gross_total,2) ?> ฿</div>
        </div>
        <div class="d-flex justify-content-between" style="margin-top:6px">
          <div class="font-weight-bold text-success">โปรนี้ช่วยประหยัด</div>
          <div class="font-weight-bold text-success">-<?= number_format($discount_total,2) ?> ฿</div>
        </div>
        <hr class="my-2">
        <div class="d-flex justify-content-between">
          <div class="h6 mb-0" style="font-weight:900;color:#0D4071">ยอดสุทธิ</div>
          <div class="h5 mb-0" style="font-weight:900;color:#2c8bd6"><?= number_format($net_total,2) ?> ฿</div>
        </div>
        <small class="text-muted d-block mt-1">* ยอดสุทธิด้านบนเป็นยอดที่หักโปรแล้ว</small>
      </div>

      <div class="p-3">
        <div class="d-flex">
          <button class="btn btn-light mr-2" form="frmCart" style="font-weight:800">อัปเดตจำนวน</button>
          <form method="post" class="m-0 flex-fill">
            <input type="hidden" name="action" value="checkout">
            <button class="btn btn-success btn-block" id="btnCheckout" style="font-weight:900; letter-spacing:.2px">สั่งออเดอร์ (F2)</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="px-3 pb-3 text-light" style="opacity:.9">ยังไม่มีสินค้าในออเดอร์</div>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}

/* ---------- Cart session ---------- */
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

/* ---------- Actions ---------- */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$success_msg = '';

/* ---------- Pay by CASH (ไม่ต้องอัปโหลดสลิป) ---------- */
if ($action === 'pay_cash') {
  header('Content-Type: application/json; charset=utf-8');

  $order_id = (int)($_POST['order_id'] ?? 0);
  if ($order_id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ข้อมูลออเดอร์ไม่ถูกต้อง']); exit; }

  $stmt = $conn->prepare("SELECT order_id FROM orders WHERE order_id=?");
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $has = (bool)$stmt->get_result()->fetch_row();
  $stmt->close();
  if (!$has) { echo json_encode(['ok'=>false,'msg'=>'ไม่พบออเดอร์']); exit; }

  $poke = $conn->prepare("UPDATE orders SET updated_at = NOW() WHERE order_id = ?");
  $poke->bind_param("i", $order_id);
  $poke->execute();
  $ok = $poke->affected_rows > 0;
  $poke->close();

  if ($ok) { $_SESSION['cart'] = []; }

  echo json_encode(['ok'=>$ok,'msg'=>$ok?'บันทึกชำระเงินสดแล้ว':'บันทึกไม่สำเร็จ','order_id'=>$order_id]);
  exit;
}

/* ---------- Upload Slip (AJAX) ---------- */
if ($action === 'upload_slip') {
  header('Content-Type: application/json; charset=utf-8');

  $order_id = (int)($_POST['order_id'] ?? 0);
  if ($order_id <= 0) { echo json_encode(['ok'=>false,'msg'=>'ข้อมูลออเดอร์ไม่ถูกต้อง']); exit; }

  $stmt = $conn->prepare("SELECT order_id, user_id, total_price, status FROM orders WHERE order_id=?");
  $stmt->bind_param("i", $order_id);
  $stmt->execute();
  $order = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$order) { echo json_encode(['ok'=>false,'msg'=>'ไม่พบออเดอร์']); exit; }

  if (!isset($_FILES['slip']) || $_FILES['slip']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'msg'=>'อัปโหลดไฟล์ไม่สำเร็จ']); exit;
  }

  $conn->query("
  CREATE TABLE IF NOT EXISTS payment_slips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime VARCHAR(64) NOT NULL,
    size_bytes INT NOT NULL,
    uploaded_at DATETIME NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    INDEX(order_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $base = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
  $dir  = $base . '/slips';
  if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

  $tmp  = $_FILES['slip']['tmp_name'];
  $size = (int)$_FILES['slip']['size'];
  if ($size > 5 * 1024 * 1024) { echo json_encode(['ok'=>false,'msg'=>'ไฟล์เกิน 5MB']); exit; }

  $fi   = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($tmp);
  $allow = ['image/jpeg','image/png','image/webp','image/heic','image/heif'];
  if (!in_array($mime, $allow, true)) { echo json_encode(['ok'=>false,'msg'=>'รองรับ JPG/PNG/WebP/HEIC เท่านั้น']); exit; }

  $target = $dir . '/slip_'.$order_id.'_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.jpg';

  $ok = false;
  try {
    switch ($mime) {
      case 'image/jpeg': $im = imagecreatefromjpeg($tmp); break;
      case 'image/png':  $im = imagecreatefrompng($tmp); imagepalettetotruecolor($im); imagealphablending($im,true); imagesavealpha($im,false); break;
      case 'image/webp': $im = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmp) : null; break;
      default: $im = null;
    }
    if ($im) {
      $w = imagesx($im); $h = imagesy($im);
      $maxSide = 1500;
      if (max($w,$h) > $maxSide) {
        $ratio = min($maxSide/$w, $maxSide/$h);
        $nw = (int)round($w*$ratio); $nh = (int)round($h*$ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $im, 0,0,0,0, $nw,$nh, $w,$h);
        imagedestroy($im); $im = $dst;
      }
      imagejpeg($im, $target, 82); imagedestroy($im);
      $ok = file_exists($target);
    } else {
      $ok = move_uploaded_file($tmp, $target);
    }
  } catch (Throwable $e) {
    $ok = move_uploaded_file($tmp, $target);
  }

  if (!$ok) { echo json_encode(['ok'=>false,'msg'=>'บันทึกไฟล์ไม่สำเร็จ']); exit; }

  $rel = 'uploads/slips/'.basename($target);
  $note = trim((string)($_POST['note'] ?? ''));
  $uid  = (int)$_SESSION['uid'];
  $sz   = filesize($target) ?: $size;

  $stmt = $conn->prepare("INSERT INTO payment_slips (order_id,user_id,file_path,mime,size_bytes,uploaded_at,note)
                          VALUES (?,?,?,?,?,NOW(),?)");
  $poke = $conn->prepare("UPDATE orders SET updated_at = NOW() WHERE order_id = ?");
  $poke->bind_param("i", $order_id);
  $poke->execute();
  $poke->close();

  $mimeSave = 'image/jpeg';
  $stmt->bind_param("iissis", $order_id, $uid, $rel, $mimeSave, $sz, $note);
  $stmt->execute(); $stmt->close();

  $_SESSION['cart'] = [];

  echo json_encode(['ok'=>true,'msg'=>'อัปโหลดสำเร็จ','path'=>$rel,'order_id'=>$order_id]);
  exit;
}

/* ---------- Add/Update/Remove/Checkout ---------- */
if ($action === 'add') {
  $menu_id = (int)($_POST['menu_id'] ?? 0);
  $qty     = max(1, (int)($_POST['qty'] ?? 1));
  $note    = trim((string)($_POST['note'] ?? ''));
  $isEdit  = isset($_POST['edit']) && (int)$_POST['edit'] === 1;
  $old_key = (string)($_POST['old_key'] ?? '');

  $addon_total = isset($_POST['addon_total']) ? (float)$_POST['addon_total'] : 0.0;
  if ($addon_total < 0) $addon_total = 0.0;

  $stmt = $conn->prepare("SELECT menu_id, name, price, image FROM menu WHERE menu_id=?");
  $stmt->bind_param("i", $menu_id);
  $stmt->execute();
  $item = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($item) {
    $picked = isset($_POST['toppings']) ? (array)$_POST['toppings'] : [];
    $picked_ids = array_values(array_filter(array_map('intval', $picked)));
    $tp = toppings_info($conn, $menu_id, $picked_ids); // ['extra'=>float, 'names'=>string[]]

    if (!empty($tp['names'])) {
      if (mb_stripos($note, 'ท็อปปิง:') === false) {
        $note = trim($note);
        $note = $note !== '' ? ($note.' | ท็อปปิง: '.implode(', ', $tp['names'])) : ('ท็อปปิง: '.implode(', ', $tp['names']));
      }
    }

    $addon_total = isset($_POST['addon_total']) ? (float)$_POST['addon_total'] : 0.0;
    if ($addon_total < 0) $addon_total = 0.0;
    $addon_effective = ($tp['extra'] > 0) ? $tp['extra'] : $addon_total;

    // ==== คิดส่วนลดโปรจาก "ราคาเมนูฐาน" ====
    $base_price = (float)$item['price'];
    $appliedPromo = best_item_promo($conn, (int)$menu_id, $base_price);
    $unit_discount = $appliedPromo ? (float)$appliedPromo['amount'] : 0.0;

    // ราคาต่อหน่วยจริง = (ราคาเมนูฐาน + ท็อปปิง) - ส่วนลด (ไม่ติดลบ)
    $unit_price = max(0.0, $base_price + $addon_effective - $unit_discount);

    $new_key = cart_key($menu_id, $note);

    if ($isEdit) {
      if ($old_key !== '' && isset($_SESSION['cart'][$old_key])) unset($_SESSION['cart'][$old_key]);
      if (isset($_SESSION['cart'][$new_key])) {
        $_SESSION['cart'][$new_key]['qty']  += $qty;
        $_SESSION['cart'][$new_key]['note']  = $note;
        $_SESSION['cart'][$new_key]['price'] = $unit_price;

        $_SESSION['cart'][$new_key]['promo_id']      = $appliedPromo ? (int)$appliedPromo['promo_id'] : null;
        $_SESSION['cart'][$new_key]['promo_name']    = $appliedPromo ? (string)$appliedPromo['name']  : '';
        $_SESSION['cart'][$new_key]['unit_discount'] = $unit_discount;
      } else {
        $_SESSION['cart'][$new_key] = [
          'menu_id' => $menu_id,
          'name'    => $item['name'],
          'price'   => $unit_price,
          'qty'     => $qty,
          'image'   => (string)$item['image'],
          'note'    => $note,
          'promo_id'      => $appliedPromo ? (int)$appliedPromo['promo_id'] : null,
          'promo_name'    => $appliedPromo ? (string)$appliedPromo['name']  : '',
          'unit_discount' => $unit_discount,
        ];
      }
    } else {
      if (isset($_SESSION['cart'][$new_key])) {
        $_SESSION['cart'][$new_key]['qty']  += $qty;
        $_SESSION['cart'][$new_key]['price'] = $unit_price;

        $_SESSION['cart'][$new_key]['promo_id']      = $appliedPromo ? (int)$appliedPromo['promo_id'] : null;
        $_SESSION['cart'][$new_key]['promo_name']    = $appliedPromo ? (string)$appliedPromo['name']  : '';
        $_SESSION['cart'][$new_key]['unit_discount'] = $unit_discount;
      } else {
        $_SESSION['cart'][$new_key] = [
          'menu_id' => $menu_id,
          'name'    => $item['name'],
          'price'   => $unit_price,
          'qty'     => $qty,
          'image'   => (string)$item['image'],
          'note'    => $note,
          'promo_id'      => $appliedPromo ? (int)$appliedPromo['promo_id'] : null,
          'promo_name'    => $appliedPromo ? (string)$appliedPromo['name']  : '',
          'unit_discount' => $unit_discount,
        ];
      }
    }
  }

  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok'    => true,
      'count' => isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0,
    ]);
    exit;
  }
}

if ($action === 'update') {
  foreach ($_POST['qty'] ?? [] as $key=>$q) {
    $q = max(0, (int)$q);
    if (isset($_SESSION['cart'][$key])) {
      if ($q===0) unset($_SESSION['cart'][$key]);
      else $_SESSION['cart'][$key]['qty'] = $q;
    }
  }
}

if ($action === 'remove') {
  $key = (string)($_GET['key'] ?? '');
  if ($key !== '' && isset($_SESSION['cart'][$key])) unset($_SESSION['cart'][$key]);
}

if ($action === 'clear') { $_SESSION['cart'] = []; }

/* ----- CHECKOUT ----- */
$new_order_id = 0; $new_total = 0.00;
if ($action === 'checkout' && !empty($_SESSION['cart'])) {
  $total = 0.00;
  foreach ($_SESSION['cart'] as $row) $total += ((float)$row['price']) * ((int)$row['qty']);

  $stmt = $conn->prepare("INSERT INTO orders (user_id, order_time, status, total_price)
                          VALUES (?, NOW(), 'pending', ?)");
  $stmt->bind_param("id", $_SESSION['uid'], $total);
  $stmt->execute();
  $order_id = $stmt->insert_id;
  $stmt->close();

  // บันทึกรายการ + promo_id (ใส่ NULL ถ้าไม่มีโปร)
  foreach ($_SESSION['cart'] as $row) {
    $line = ((int)$row['qty']) * ((float)$row['price']); // price = หลังหักโปรแล้ว
    $promoId = $row['promo_id'] ?? null;

    if ($promoId === null) {
      $stmt = $conn->prepare("INSERT INTO order_details (order_id, menu_id, promo_id, quantity, note, total_price)
                              VALUES (?, ?, NULL, ?, ?, ?)");
      $stmt->bind_param("iiisd", $order_id, $row['menu_id'], $row['qty'], $row['note'], $line);
    } else {
      $stmt = $conn->prepare("INSERT INTO order_details (order_id, menu_id, promo_id, quantity, note, total_price)
                              VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("iiiisd", $order_id, $row['menu_id'], $promoId, $row['qty'], $row['note'], $line);
    }
    $stmt->execute();
    $stmt->close();
  }

  $new_order_id = $order_id;
  $new_total    = $total;
}

/* ---------- AJAX: กล่องตะกร้า ---------- */
if ($action === 'cart_html') {
  header('Content-Type: text/html; charset=utf-8');
  echo render_cart_box();
  exit;
}

/* ---------- Data ---------- */
$cat_raw     = $_GET['category_id'] ?? '0';
$isTop       = ($cat_raw === 'top');
$category_id = $isTop ? 0 : (int)$cat_raw;
$keyword     = trim((string)($_GET['q'] ?? ''));

$paid_flag = isset($_GET['paid']) ? (int)$_GET['paid'] : 0;
$paid_oid  = isset($_GET['oid'])  ? (int)$_GET['oid']  : 0;
if ($paid_flag === 1 && $paid_oid > 0) {
  $success_msg = "สั่งออเดอร์แล้ว! เลขที่ออเดอร์ #{$paid_oid}";
}

$cats = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_id");

/* ===== Active item promotions per menu (เลือกโปรที่ลด 'จำนวนเงิน' สูงสุด) ===== */
$promoJoin = "
LEFT JOIN (
  SELECT
    pi.menu_id,

    -- id โปรที่ดีที่สุด
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.promo_id ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS best_promo_id,

    -- ชื่อโปรที่ดีที่สุด
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.name ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS promo_name,

    -- ประเภทส่วนลด (PERCENT/FIXED) ของโปรที่ดีที่สุด
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.discount_type ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS discount_type,

    -- ค่าที่ใช้ลด (เช่น 5.00 ถ้าเป็นเปอร์เซ็นต์ก็คือ 5%)
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.discount_value ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS discount_value,

    -- จำนวนเงินที่ลดได้ (คำนวณบนราคาเมนู m.price)
    MAX(
      LEAST(
        CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
        COALESCE(p.max_discount, 999999999)
      )
    ) AS discount_amount,

    -- ขอบเขตโปร (ITEM/ORDER)
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.scope ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS promo_scope,

    -- เวลาเริ่ม/จบของโปรที่ดีที่สุด
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        DATE_FORMAT(p.start_at, '%Y-%m-%d %H:%i:%s') ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS promo_start_at,

    SUBSTRING_INDEX(
      GROUP_CONCAT(
        DATE_FORMAT(p.end_at, '%Y-%m-%d %H:%i:%s') ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS promo_end_at,

    -- สถานะโปร (1=active)
    SUBSTRING_INDEX(
      GROUP_CONCAT(
        p.is_active ORDER BY
          LEAST(
            CASE WHEN p.discount_type='PERCENT' THEN (p.discount_value/100.0)*m.price ELSE p.discount_value END,
            COALESCE(p.max_discount, 999999999)
          ) DESC
        SEPARATOR ','
      ),
      ',', 1
    ) AS promo_is_active

  FROM promotion_items pi
  JOIN promotions p ON p.promo_id = pi.promo_id
  JOIN menu m       ON m.menu_id = pi.menu_id
  WHERE p.is_active = 1 AND p.scope='ITEM' AND NOW() BETWEEN p.start_at AND p.end_at
  GROUP BY pi.menu_id
) ap ON ap.menu_id = m.menu_id
";


/* ----- เมนู (ยอดนิยม/ปกติ) พร้อมคอลัมน์โปรโมชัน ----- */
if ($isTop) {
  // ✅ ยอดนิยม: ดึงทุกเมนูที่ active แล้วคำนวณยอดขาย (ถ้าไม่เคยขาย = 0)
  $sql = "SELECT 
            m.menu_id, m.name, m.price, m.image, c.category_name,
            (SELECT COALESCE(SUM(d.quantity),0)
               FROM order_details d
              WHERE d.menu_id = m.menu_id
            ) AS total_sold,
            ap.best_promo_id, ap.promo_name, ap.discount_type, ap.discount_value, ap.discount_amount
          FROM menu m
          LEFT JOIN categories c ON m.category_id = c.category_id
          $promoJoin
          WHERE m.is_active = 1";
  $types = ''; $params = [];

  if ($keyword !== '') { 
    $sql .= " AND m.name LIKE ?"; 
    $types .= 's'; 
    $params[] = '%'.$keyword.'%'; 
  }

  $sql .= " ORDER BY total_sold DESC, m.menu_id ASC LIMIT 12";

  if ($types !== '') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); 
    $menus = $stmt->get_result(); 
    $stmt->close();
  } else {
    $menus = $conn->query($sql);
  }

  // ✅ Fallback: ถ้าไม่มีเมนูเลย → ดึงทั้งหมดแทน (ใส่เงื่อนไขก่อน ORDER BY เสมอ)
  if ($menus && $menus->num_rows === 0) {
    $sqlAll = "SELECT 
                 m.menu_id, m.name, m.price, m.image, c.category_name,
                 ap.best_promo_id, ap.promo_name, ap.discount_type, ap.discount_value, ap.discount_amount
               FROM menu m
               LEFT JOIN categories c ON m.category_id=c.category_id
               $promoJoin
               WHERE m.is_active = 1";

    $typesAll = ''; 
    $paramsAll = [];

    if ($keyword !== '') {
      $sqlAll   .= " AND m.name LIKE ?";
      $typesAll .= 's';
      $paramsAll[] = '%'.$keyword.'%';
    }

    $sqlAll .= " ORDER BY m.menu_id";

    if ($typesAll !== '') {
      $stmt = $conn->prepare($sqlAll);
      $stmt->bind_param($typesAll, ...$paramsAll);
      $stmt->execute();
      $menus = $stmt->get_result();
      $stmt->close();
    } else {
      $menus = $conn->query($sqlAll);
    }
  }

} else {
  // ✅ ปกติ: ตามหมวด/คำค้น
  $sql = "SELECT 
            m.menu_id, m.name, m.price, m.image, c.category_name,
            ap.best_promo_id, ap.promo_name, ap.discount_type, ap.discount_value, ap.discount_amount
          FROM menu m 
          LEFT JOIN categories c ON m.category_id=c.category_id
          $promoJoin
          WHERE m.is_active = 1";
  $types=''; $params=[];
  if ($category_id>0) { $sql.=" AND m.category_id=?"; $types.='i'; $params[]=$category_id; }
  if ($keyword!=='')  { $sql.=" AND m.name LIKE ?";   $types.='s'; $params[]='%'.$keyword.'%'; }
  $sql .= " ORDER BY m.menu_id";
  if ($types!=='') {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); 
    $menus = $stmt->get_result(); 
    $stmt->close();
  } else {
    $menus = $conn->query($sql);
  }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>PSU Blue Cafe • Menu</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet"
 href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<style>
  /* PSU Radio Style */
.psu-radio{display:inline-flex;align-items:center;background:#f6faff;border:2px solid #29ABE2;border-radius:999px;padding:8px 14px;font-weight:700;color:#0D4071;cursor:pointer;transition:all .2s}
.psu-radio input{display:none}
.psu-radio span{font-size:.95rem}
.psu-radio:hover{background:#eaf4ff;border-color:#0094B3}
.psu-radio input:checked + span{color:#fff;background:linear-gradient(180deg,#4173BD,#0D4071);padding:6px 12px;border-radius:999px}

/* Payment Theme */
#uploadZone,#cashZone{font-family:"Segoe UI",Tahoma,sans-serif;color:#0D4071}
#uploadZone label,#cashZone label{font-weight:700;color:#4173BD}
#dropzone{border:2px dashed #29ABE2;background:#f6faff;color:#0D4071;transition:all .2s}
#dropzone:hover{background:#e9f3ff;border-color:#0094B3}
#dropzone .lead{color:#0D4071;font-weight:800}
#dropzone .small{color:#6b7280}
#btnChoose{background:linear-gradient(180deg,#29ABE2,#0094B3);border:1px solid #0D4071;font-weight:700}
#btnChoose:hover{background:#4EC5E0;color:#002b4a}
#btnUpload,#btnCashConfirm{background:linear-gradient(180deg,#4173BD,#0D4071);border:none;font-weight:900;letter-spacing:.5px}
#btnUpload:hover,#btnCashConfirm:hover{background:linear-gradient(180deg,#29ABE2,#0094B3)}
#btnSlipCancel,#btnSlipCancel2{border:1px solid #BBB4D8;color:#0D4071;font-weight:700}
#btnSlipCancel:hover,#btnSlipCancel2:hover{background:#eaf4ff}
#cashZone .alert-info{background:#eaf4ff;border:1px solid #29ABE2;color:#0D4071;font-weight:700;border-radius:12px}

.topbar-actions{gap:8px}
.topbar .btn-primary{background:linear-gradient(180deg,#3aa3ff,#1f7ee8);border-color:#1669c9;font-weight:800}
@media (max-width:576px){.topbar{flex-wrap:wrap;gap:8px}.topbar-actions{width:100%;justify-content:flex-end}}

/* Modal */
.psu-modal{position:fixed;inset:0;display:none;z-index:1050}
.psu-modal.is-open{display:block}
.psu-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px)}
.psu-modal__dialog{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:min(1020px,96vw);max-height:92vh;overflow:auto;background:#fff;border-radius:20px;box-shadow:0 22px 66px rgba(0,0,0,.45);border:1px solid #cfe3ff}
.psu-modal__body{padding:0}
.psu-modal__close{position:absolute;right:12px;top:8px;border:0;background:transparent;font-size:32px;font-weight:900;line-height:1;cursor:pointer;color:#08345c}

/* Theme */
:root{
  --psu-deep-blue:#0D4071; --psu-ocean-blue:#4173BD; --psu-andaman:#0094B3;
  --psu-sky:#29ABE2; --psu-river:#4EC5E0; --psu-sritrang:#BBB4D8;
  --bg-grad1: var(--psu-deep-blue); --bg-grad2: var(--psu-ocean-blue);
  --ink:#0b2746; --shadow:0 14px 32px rgba(0,0,0,.24); --ring:#7dd3fc;
}
html,body{height:100%}
body{background:linear-gradient(135deg,var(--bg-grad1),var(--bg-grad2));color:#fff;font-family:"Segoe UI",Tahoma,Arial,sans-serif}

/* Layout */
.pos-shell{padding:12px;max-width:1600px;margin:0 auto;}
.topbar{position:sticky;top:0;z-index:50;padding:12px 16px;border-radius:14px;background:rgba(13,64,113,.92);backdrop-filter:blur(6px);border:1px solid rgba(187,180,216,.25);box-shadow:0 8px 20px rgba(0,0,0,.18)}
.brand{font-weight:900;letter-spacing:.3px}

/* Buttons */
.btn-ghost{background:var(--psu-andaman);border:1px solid #063d63;color:#fff;font-weight:700}
.btn-ghost:hover{background:var(--psu-sky);color:#002b4a}
.btn-primary,.btn-success{font-weight:800}

/* Chips & card */
.pos-card{background:rgba(255,255,255,.08);border:1px solid var(--psu-sritrang);border-radius:16px;box-shadow:var(--shadow)}
.chips a{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;margin:0 8px 10px 0;border-radius:999px;border:1px solid var(--psu-ocean-blue);color:#fff;text-decoration:none;font-weight:700;background:rgba(255,255,255,.05)}
.chips a.active{background:linear-gradient(180deg,var(--psu-sky),var(--psu-river));color:#062d4f;border-color:#073c62;box-shadow:0 8px 18px rgba(0,0,0,.15)}
.chips a:hover{transform:translateY(-1px)}

/* Search */
.searchbox{background:#fff;border:2px solid var(--psu-ocean-blue);color:#000;border-radius:999px;padding:.4rem .9rem;min-width:260px}
.searchbox:focus{box-shadow:0 0 0 .2rem rgba(41,171,226,.35)}

/* Grid */
.menu-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;padding:12px}
@media (min-width:768px) and (max-width:1399px){.menu-grid{grid-template-columns:repeat(3,1fr)}}
@media (max-width:767px){.menu-grid{grid-template-columns:repeat(2,1fr)}}

.product-mini{background:#fff;border:1px solid #e3ecff;border-radius:14px;overflow:hidden;color:inherit;text-decoration:none;display:flex;flex-direction:column;height:100%;transition:transform .12s,box-shadow .12s,border-color .12s}
.product-mini:focus,.product-mini:hover{transform:translateY(-2px);border-color:#bed7ff;box-shadow:0 10px 20px rgba(0,0,0,.16);outline:none}
.product-mini .thumb{width:100%;height:120px;object-fit:cover;background:#eaf4ff}
.product-mini .meta{padding:10px 12px 12px}
.product-mini .pname{font-weight:900;color:#0D4071;line-height:1.15;font-size:1.0rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:2.3em}
.product-mini .row2{display:flex;align-items:center;justify-content:space-between;margin-top:8px}
.product-mini .pprice{font-weight:900;color:#29ABE2;font-size:1.05rem;letter-spacing:.2px}
.product-mini .quick{font-size:.85rem;font-weight:800;padding:6px 12px;border-radius:999px;background:#0094B3;border:1px solid #0a3e62;color:#fff}
.product-mini .quick:hover{background:#29ABE2;color:#002a48}

/* Cart */
.cart{position:sticky;top:82px}
.table-cart{color:#0b2746;background:#fff;border-radius:12px;overflow:hidden;table-layout:auto}
.table-cart thead th{background:#f5f9ff;color:#06345c;border-bottom:2px solid #e7eefc;font-weight:800}
.table-cart td,.table-cart th{border-color:#e7eefc!important}
.table-cart thead th:first-child,.table-cart tbody td:first-child{width:58%}
.note-list{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.note-pill{display:inline-flex;align-items:center;background:#eef6ff;border:1px solid #cfe2ff;border-radius:999px;padding:4px 10px;font-size:.82rem;font-weight:800}
.note-pill .k{color:#194bd6;margin-right:6px}.note-pill .v{color:#0D4071}
.table-cart tbody tr:not(:last-child) td{border-bottom:2px dashed #0066ff!important}
.cart-footer{background:linear-gradient(180deg,var(--psu-ocean-blue),var(--psu-deep-blue));color:#fff;border-top:1px solid #0D4071;padding:12px 14px;border-radius:0 0 14px 14px}
.total-tag{font-size:1.35rem;font-weight:900;color:#4EC5E0}

.alert-ok{background:#2e7d32;color:#fff;border:none}
.badge-user{background:var(--psu-ocean-blue);color:#fff;font-weight:800;border-radius:999px}

:focus-visible{outline:3px solid var(--ring);outline-offset:2px;border-radius:10px}
*::-webkit-scrollbar{width:10px;height:10px}
*::-webkit-scrollbar-thumb{background:#2b568a;border-radius:10px}
*::-webkit-scrollbar-thumb:hover{background:#2f6db5}
*::-webkit-scrollbar-track{background:rgba(255,255,255,.08)}

.voice-toggle{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.25);font-weight:800}
</style>
</head>
<body>
<div class="container-fluid pos-shell">

  <!-- Top bar -->
  <div class="topbar d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex align-items-center">
      <h4 class="brand mb-0 mr-3">PSU Blue Cafe • Menu</h4>

      <form class="form-inline" method="get" action="front_store.php">
        <input name="q" value="<?= htmlspecialchars($keyword,ENT_QUOTES,'UTF-8') ?>" class="form-control form-control-sm searchbox mr-2" type="search" placeholder="ค้นหารายการ (กด / เพื่อค้นหา)">
        <?php if($category_id>0){ ?><input type="hidden" name="category_id" value="<?= (int)$category_id ?>"><?php } ?>
        <?php if($isTop){ ?><input type="hidden" name="category_id" value="top"><?php } ?>
        <button class="btn btn-sm btn-ghost">ค้นหา</button>
      </form>
    </div>

    <div class="d-flex align-items-center topbar-actions">
      <label class="voice-toggle mr-2 mb-0">
        <input type="checkbox" id="voiceSwitch" class="mr-1">
        เสียงแจ้งเตือน
      </label>

      <a href="checkout.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">Order</a>
      <a href="../SelectRole/role.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">ตําเเหน่ง</a>
       <a href="user_profile.php" class="btn btn-primary btn-sm mr-2" style="font-weight:800">ข้อมูลส่วนตัว</a>
      <span class="badge badge-user px-3 py-2 mr-2">ผู้ใช้: <?= htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES,'UTF-8') ?></span>
      <a class="btn btn-sm btn-outline-light" href="../logout.php">ออกจากระบบ</a>
    </div>
  </div>

  <?php if ($success_msg): ?>
    <div class="alert alert-ok pos-card p-3 mb-3">
      <?= htmlspecialchars($success_msg, ENT_QUOTES, 'UTF-8') ?>
      &nbsp;&nbsp;<a class="btn btn-light btn-sm" href="checkout.php">ไปหน้า Order</a>
    </div>
  <?php endif; ?>

  <?php if (!empty($new_order_id)): ?>
  <script>
    window.addEventListener('load', () => {
      const oid  = <?= (int)$new_order_id ?>;
      const amt  = "<?= isset($new_total)? number_format((float)$new_total,2): '0.00' ?>";
      if (typeof openSlipModal === 'function') openSlipModal(oid, amt);
      else setTimeout(() => { try { openSlipModal(oid, amt); } catch (_) {} }, 300);
    });
  </script>
  <?php endif; ?>

  <!-- CHIPS -->
  <div class="pos-card p-3 mb-3">
    <div class="d-flex align-items-center flex-wrap chips">
      <div class="mr-2 text-white-50 font-weight-bold">หมวดหมู่:</div>

      <?php $topLink = 'front_store.php?category_id=top' . ($keyword!==''?('&q='.urlencode($keyword)):''); ?>
      <a href="<?= htmlspecialchars($topLink,ENT_QUOTES,'UTF-8') ?>" class="<?= $isTop ? 'active' : '' ?>">ยอดนิยม</a>

      <a href="front_store.php<?= $keyword!==''?('?q='.urlencode($keyword)) : '' ?>" class="<?= (!$isTop && $category_id===0)?'active':'' ?>">ทั้งหมด</a>

      <?php while($c=$cats->fetch_assoc()):
        $link = "front_store.php?category_id=".(int)$c['category_id'].($keyword!==''?('&q='.urlencode($keyword)):''); ?>
        <a href="<?= htmlspecialchars($link,ENT_QUOTES,'UTF-8') ?>" class="<?= (!$isTop && $category_id===(int)$c['category_id'])?'active':'' ?>">
          <?= htmlspecialchars($c['category_name'],ENT_QUOTES,'UTF-8') ?>
        </a>
      <?php endwhile; ?>
    </div>
  </div>

  <div class="row">
    <!-- เมนู -->
    <div class="col-xl-8 col-lg-8 col-md-7 mb-3">
      <div class="pos-card">
        <?php if($menus && $menus->num_rows>0): ?>
          <div class="menu-grid">
            <?php while($m=$menus->fetch_assoc()):
              $img = trim((string)$m['image']);
              $imgPathFs = __DIR__ . "/../admin/images/" . ($img !== '' ? $img : "default.png");
              $imgSrc    = "../admin/images/" . ($img !== '' ? $img : "default.png");
              if (!file_exists($imgPathFs)) $imgSrc = "https://via.placeholder.com/600x400?text=No+Image";

              $hasPromo  = isset($m['discount_amount']) && (float)$m['discount_amount'] > 0;
              $final     = $hasPromo ? max(0, (float)$m['price'] - (float)$m['discount_amount']) : (float)$m['price'];

              $promoTag = '';
              if ($hasPromo) {
                if ((string)$m['discount_type'] === 'PERCENT') {
                  $pct = rtrim(rtrim(number_format((float)$m['discount_value'], 2, '.', ''), '0'), '.');
                  $promoTag = ($m['promo_name'] ? $m['promo_name'].' ' : '')."-{$pct}%";
                } else {
                  $promoTag = ($m['promo_name'] ? $m['promo_name'].' ' : '').'-'.number_format((float)$m['discount_amount'], 2).'฿';
                }
              }
            ?>
              <a class="product-mini" href="menu_detail.php?id=<?= (int)$m['menu_id'] ?>" data-id="<?= (int)$m['menu_id'] ?>" tabindex="0">
                <img class="thumb" src="<?= htmlspecialchars($imgSrc,ENT_QUOTES,'UTF-8') ?>" alt="">
                <div class="meta">
                  <div class="pname"><?= htmlspecialchars($m['name'],ENT_QUOTES,'UTF-8') ?></div>

                  <?php if ($hasPromo): ?>
                    <div class="mt-1">
                      <span class="badge badge-success" style="font-weight:800">
                        โปร: <?= htmlspecialchars($promoTag, ENT_QUOTES, 'UTF-8') ?>
                      </span>
                    </div>
                  <?php endif; ?>

<?php
$promoScope   = $m['promo_scope'] ?? '';
$promoType    = $m['discount_type'] ?? '';
$promoValue   = isset($m['discount_value']) ? (float)$m['discount_value'] : 0.0;
$promoStart   = $m['promo_start_at'] ?? '';
$promoEnd     = $m['promo_end_at'] ?? '';
$promoActive  = isset($m['promo_is_active']) ? ((int)$m['promo_is_active'] === 1) : false;

// รูปแบบตัวเลข/ข้อความ
$valText = ($promoType === 'PERCENT')
  ? rtrim(rtrim(number_format($promoValue, 2, '.', ''), '0'), '.') . '%'
  : number_format($promoValue, 2) . ' ฿';

$saveText = number_format((float)$m['discount_amount'], 2); // เงินที่ลดได้
?>
<?php if ($hasPromo): ?>
  <div class="mt-2" style="font-size:.86rem; background:#f6fbff; border:1px solid #d6e9ff; border-radius:10px; padding:8px 10px;">
    
    
  </div>
<?php endif; ?>



                  <div class="row2">
                    <div class="pprice">
                      <?php if ($hasPromo): ?>
                        <div style="line-height:1">
                          <div class="text-muted" style="text-decoration:line-through; font-weight:700;">
                            <?= money_fmt($m['price']) ?> ฿
                          </div>
                          <div style="font-weight:900;">
                            <?= money_fmt($final) ?> ฿
                          </div>
                        </div>
                      <?php else: ?>
                        <?= money_fmt($final) ?> ฿
                      <?php endif; ?>
                    </div>
                    <span class="quick">เลือก</span>
                  </div>
                </div>
              </a>
            <?php endwhile; ?>
          </div>
        <?php else: ?>
          <div class="p-3"><div class="alert alert-warning m-0">ไม่พบสินค้า</div></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ตะกร้า -->
    <div class="col-xl-4 col-lg-4 col-md-5 mb-3">
      <div id="cartBox">
        <?= render_cart_box(); ?>
      </div>
    </div>
  </div>
</div>

<!-- ===== Menu Detail Modal ===== -->
<div id="menuModal" class="psu-modal" aria-hidden="true">
  <div class="psu-modal__backdrop"></div>
  <div class="psu-modal__dialog">
    <button type="button" class="psu-modal__close" id="menuModalClose" aria-label="Close">&times;</button>
    <div class="psu-modal__body" id="menuModalBody">
      <div class="text-center py-5">กำลังโหลด…</div>
    </div>
  </div>
</div>

<!-- ===== Slip Upload / Payment Modal ===== -->
<div id="slipModal" class="psu-modal" aria-hidden="true">
  <div class="psu-modal__backdrop"></div>
  <div class="psu-modal__dialog" style="max-width:720px">
    <button type="button" class="psu-modal__close" id="slipClose" aria-label="Close">&times;</button>
    <div class="psu-modal__body" id="slipBody">
      <div class="p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="h5 mb-0">ยืนยันการชำระเงิน</div>
          <span class="badge badge-primary" id="slipBadge" style="font-size:.95rem"></span>
        </div>
        <div class="text-muted mb-3">ยอดที่ต้องชำระ: <strong id="slipAmount">0.00</strong> ฿</div>

        <div class="form-group mb-2">
          <label class="font-weight-bold d-block mb-2">วิธีชำระ</label>
          <div class="d-flex">
            <label class="psu-radio mr-3">
              <input type="radio" name="pmethod" value="transfer" checked>
              <span>💳 โอนเงิน (อัปโหลดสลิป)</span>
            </label>
            <label class="psu-radio">
              <input type="radio" name="pmethod" value="cash">
              <span>💵 เงินสด (ไม่ต้องแนบสลิป)</span>
            </label>
          </div>
        </div>

        <div id="uploadZone">
          <form id="frmSlip" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_slip">
            <input type="hidden" name="order_id" id="slipOrderId" value="">
            <div class="mb-2" id="dropzone" style="border:2px dashed #8bb6ff; border-radius:12px; padding:16px; background:#f6faff; text-align:center; cursor:pointer">
              <div class="lead mb-1">ลากไฟล์มาวางที่นี่ หรือกดเลือกไฟล์</div>
              <div class="small text-muted">รองรับ JPG, PNG, WebP, HEIC ขนาดไม่เกิน 5MB</div>
              <input type="file" name="slip" id="slipFile" accept="image/*" capture="environment" class="d-none">
              <button type="button" class="btn btn-info mt-2" id="btnChoose">เลือกไฟล์ / ถ่ายภาพ</button>
            </div>

            <div class="form-group">
              <label>หมายเหตุ (ถ้ามี)</label>
              <input type="text" name="note" class="form-control" placeholder="เช่น โอนจากธนาคาร... เวลา ...">
            </div>

            <div id="slipPreview" style="display:flex; gap:10px; flex-wrap:wrap; margin:10px 0"></div>

            <div class="d-flex">
              <button class="btn btn-success mr-2" type="submit" id="btnUpload">อัปโหลดสลิป</button>
              <button class="btn btn-outline-secondary" type="button" id="btnSlipCancel">ยกเลิก</button>
            </div>
            <div id="slipMsg" class="mt-3"></div>
          </form>
        </div>

        <div id="cashZone" style="display:none">
          <div class="alert alert-info">รับชำระเป็น <strong>เงินสด</strong> – ไม่ต้องแนบสลิป</div>
          <div class="d-flex">
            <button class="btn btn-success mr-2" id="btnCashConfirm" type="button">ยืนยันรับเงินสด</button>
            <button class="btn btn-outline-secondary" type="button" id="btnSlipCancel2">ยกเลิก</button>
          </div>
          <div id="cashMsg" class="mt-3"></div>
        </div>

      </div>
    </div>
  </div>
</div>

<!-- Hotkeys -->
<script>
document.addEventListener('keydown', function(e){
  if (e.key === '/') {
    const q = document.querySelector('input[name="q"]');
    if (q) { q.focus(); q.select(); e.preventDefault(); }
  }
  if (e.key === 'F2') {
    const btn = document.getElementById('btnCheckout');
    if (btn) { e.preventDefault(); btn.click(); }
  }
});
</script>

<!-- Toast zone + เสียงแจ้งเตือน -->
<div id="toast-zone" style="position:fixed; right:16px; bottom:16px; z-index:9999;"></div>
<audio id="ding" preload="auto">
  <source src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" type="audio/ogg">
</audio>

<script>
// ==== Voice helper ====
const voiceSwitch = document.getElementById('voiceSwitch');
const VOICE_FLAG_KEY = 'psu.voice.enabled';
try{ voiceSwitch.checked = localStorage.getItem(VOICE_FLAG_KEY) === '1'; }catch(_){}
voiceSwitch?.addEventListener('change', () => {
  try{ localStorage.setItem(VOICE_FLAG_KEY, voiceSwitch.checked ? '1':'0'); }catch(_){}
  if (voiceSwitch.checked) speakOnceWarmup();
});
function speak(text, lang='th-TH'){
  if (!('speechSynthesis' in window)) return false;
  if (!voiceSwitch?.checked) return false;
  const u = new SpeechSynthesisUtterance(text);
  u.lang = lang; u.rate = 1.0; u.pitch = 1.0;
  const pickThai = (window.speechSynthesis.getVoices() || []).find(v => /th(-|_|$)/i.test(v.lang));
  if (pickThai) u.voice = pickThai;
  window.speechSynthesis.cancel(); window.speechSynthesis.speak(u);
  return true;
}
function speakOnceWarmup(){
  try{
    const t = new SpeechSynthesisUtterance('พร้อมใช้งานเสียงแจ้งเตือนแล้ว');
    t.lang = 'th-TH'; window.speechSynthesis.cancel(); window.speechSynthesis.speak(t);
  }catch(_){}
}
</script>

<script>
// ==== Poll order status + Voice on READY ====
let lastSince = ''; let knownStatus = {};
function showToast(text, style='info'){
  const id = 't' + Date.now();
  const bg = style==='success' ? '#28a745' : (style==='danger' ? '#dc3545' : '#007bff');
  const el = document.createElement('div');
  el.id = id; el.style.cssText = `min-width:260px;margin-top:8px;background:${bg};color:#fff;padding:12px 14px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.2);font-weight:700`;
  el.textContent = text; document.getElementById('toast-zone').appendChild(el);
  setTimeout(()=> el.remove(), 4000);
}
async function poll(){
  try{
    const base = '../api/orders_feed.php?mine=1';
    const qs   = lastSince ? ('&since='+encodeURIComponent(lastSince)) : '';
    const r = await fetch(base+qs, {cache:'no-store'}); if(!r.ok) throw new Error('HTTP '+r.status);
    const data = await r.json(); if(!data.ok) return;
    if (!lastSince && data.now) lastSince = data.now;
    if(data.orders && data.orders.length){
      lastSince = data.orders[data.orders.length - 1].updated_at;
      for(const o of data.orders){
        const id = o.order_id, st = o.status;
        const prev = knownStatus[id]; knownStatus[id] = st;
        if (prev && prev !== st){
          if (st === 'ready'){
            const msg = `ออเดอร์ของคุณ หมายเลข ${id} เสร็จแล้ว!`;
            showToast(msg, 'success'); document.getElementById('ding')?.play().catch(()=>{}); speak(msg, 'th-TH');
          } else if (st === 'canceled'){
            showToast(`ออเดอร์ของคุณ #${id} ถูกยกเลิก`, 'danger');
          } else { showToast(`ออเดอร์ของคุณ #${id} → ${st}`); }
        } else if (!prev) { knownStatus[id] = st; }
      }
    }
  }catch(e){ }finally{ setTimeout(poll, 1500); }
}
window.addEventListener('load', () => {
  if ('speechSynthesis' in window) { window.speechSynthesis.onvoiceschanged = () => {}; }
  poll();
});
</script>

<script>
// ===== Modal logic (เพิ่ม/แก้ไขผ่านโมดัลเมนู) =====
const modal = document.getElementById('menuModal');
const modalBody = document.getElementById('menuModalBody');
const closeBtn = document.getElementById('menuModalClose');
function openModal(){ modal.classList.add('is-open'); document.body.style.overflow='hidden'; }
function closeModal(){ modal.classList.remove('is-open'); document.body.style.overflow=''; }
closeBtn.onclick = closeModal;
document.querySelector('#menuModal .psu-modal__backdrop').addEventListener('click', closeModal);
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

async function showMenuPopup(menuId, oldKey=null){
  if(!menuId) return;
  openModal();
  modalBody.innerHTML = '<div class="text-center py-5">กำลังโหลด…</div>';
  try{
    const url = 'menu_detail.php?popup=1&id=' + encodeURIComponent(menuId) + (oldKey ? ('&edit=1&key='+encodeURIComponent(oldKey)) : '');
    const r = await fetch(url, {cache:'no-store', credentials:'same-origin'});
    const html = await r.text();
    modalBody.innerHTML = html;

    const form = modalBody.querySelector('#menuForm');
    if(form){
      const onSubmit = async (ev)=>{
        ev.preventDefault(); ev.stopPropagation();
        const fd = new FormData(form);
        fd.set('action','add');
        if(oldKey){ fd.set('edit','1'); fd.set('old_key', oldKey); }

        if(!fd.get('qty')) fd.set('qty','1');
        const mid = (fd.get('menu_id') || menuId);
        if(!fd.get('menu_id')) fd.set('menu_id', String(mid));

        const pick = (name)=> (modalBody.querySelector(`input[name="${name}"]:checked`) || {}).value || '';
        const parts = [];
        const size = pick('size'), sweet = pick('sweet'), ice = pick('ice');
        if(size)  parts.push('ขนาด: '+size);
        if(sweet) parts.push('หวาน: '+sweet);
        if(ice)   parts.push('น้ำแข็ง: '+ice);
        const tops = Array.from(modalBody.querySelectorAll('input[name="toppings[]"]:checked')).map(x=> (x.dataset?.title || x.value));
        const free = (modalBody.querySelector('textarea[name="note_free"]')?.value || '').trim();
        if(tops.length) parts.push('ท็อปปิง: '+tops.join(', '));
        if(free) parts.push('หมายเหตุ: '+free);
        if(!fd.get('note')) fd.set('note', parts.join(' | '));

        try{
          const res = await fetch('front_store.php', {
            method:'POST', body:fd, credentials:'same-origin', cache:'no-store',
            headers:{ 'X-Requested-With': 'XMLHttpRequest' }
          });
          try{ await res.json(); }catch(_){}
          closeModal();
          await refreshCart();
        }catch(err){ alert(oldKey ? 'แก้ไขรายการไม่สำเร็จ' : 'เพิ่มลงตะกร้าไม่สำเร็จ'); }
      };
      form.addEventListener('submit', onSubmit, { once:true });
    }
  }catch(err){
    modalBody.innerHTML = '<div class="p-4 text-danger">โหลดไม่สำเร็จ</div>';
  }
}

document.addEventListener('click', (e)=>{
  const card = e.target.closest('.product-mini');
  if(!card) return;
  e.preventDefault();
  const idFromData = card.dataset.id;
  const idFromHref = (()=>{ try{ return new URL(card.getAttribute('href'), location.href).searchParams.get('id'); }catch(_){ return null } })();
  const menuId = idFromData || idFromHref;
  showMenuPopup(menuId);
});

document.addEventListener('click', (e)=>{
  const editBtn = e.target.closest('.js-edit');
  if(!editBtn) return;
  e.preventDefault();
  const menuId = editBtn.getAttribute('data-menu-id');
  const oldKey = editBtn.getAttribute('data-key');
  showMenuPopup(menuId, oldKey);
});

window.addEventListener('load', ()=>{
  const mid = sessionStorage.getItem('psu.openMenuId');
  if (mid) {
    sessionStorage.removeItem('psu.openMenuId');
    showMenuPopup(mid);
  }
});

async function refreshCart(){
  try{
    const r = await fetch('front_store.php?action=cart_html', { cache:'no-store', credentials:'same-origin', headers:{ 'X-Requested-With':'XMLHttpRequest' }});
    if(!r.ok){ throw new Error('HTTP '+r.status); }
    const html = await r.text();
    const box = document.getElementById('cartBox');
    if (box) box.innerHTML = html;
  }catch(_){}
}
</script>

<script>
/* ===== Slip Modal logic ===== */
const slipModal   = document.getElementById('slipModal');
const slipBody    = document.getElementById('slipBody');
const slipClose   = document.getElementById('slipClose');
const slipOrderId = document.getElementById('slipOrderId');
const slipAmount  = document.getElementById('slipAmount');
const slipBadge   = document.getElementById('slipBadge');
const slipFile    = document.getElementById('slipFile');
const slipPrev    = document.getElementById('slipPreview');
const slipMsg     = document.getElementById('slipMsg');
const dropzone    = document.getElementById('dropzone');
const btnChoose   = document.getElementById('btnChoose');
const btnUpload   = document.getElementById('btnUpload');
const btnSlipCancel = document.getElementById('btnSlipCancel');
const frmSlip     = document.getElementById('frmSlip');

const btnSlipCancel2 = document.getElementById('btnSlipCancel2');
const cashZone   = document.getElementById('cashZone');
const uploadZone = document.getElementById('uploadZone');
const btnCashConfirm = document.getElementById('btnCashConfirm');
const cashMsg    = document.getElementById('cashMsg');

function openSlipModal(orderId, amountText){
  slipOrderId.value = String(orderId);
  slipAmount.textContent = amountText;
  slipBadge.textContent  = '#' + orderId;
  slipPrev.innerHTML = '';
  slipFile.value = '';
  slipMsg.innerHTML = '';

  const pmTransfer = document.querySelector('input[name="pmethod"][value="transfer"]');
  if (pmTransfer) pmTransfer.checked = true;
  if (uploadZone) uploadZone.style.display = '';
  if (cashZone)   cashZone.style.display   = 'none';

  slipModal.classList.add('is-open');
  document.body.style.overflow='hidden';
}
function closeSlipModal(){
  slipModal.classList.remove('is-open');
  document.body.style.overflow='';
}
slipClose.addEventListener('click', closeSlipModal);
document.querySelector('#slipModal .psu-modal__backdrop').addEventListener('click', closeSlipModal);
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeSlipModal(); });

btnSlipCancel.addEventListener('click', closeSlipModal);
btnSlipCancel2?.addEventListener('click', closeSlipModal);
btnChoose.addEventListener('click', () => slipFile.click());
dropzone.addEventListener('click', () => slipFile.click());

document.addEventListener('change', (e)=>{
  if (e.target && e.target.name === 'pmethod') {
    const v = e.target.value;
    if (uploadZone) uploadZone.style.display = (v === 'transfer') ? '' : 'none';
    if (cashZone)   cashZone.style.display   = (v === 'cash')     ? '' : 'none';
  }
});

;['dragenter','dragover'].forEach(ev => dropzone.addEventListener(ev, e=>{
  e.preventDefault(); e.stopPropagation(); dropzone.style.background='#e9f3ff';
}));
;['dragleave','drop'].forEach(ev => dropzone.addEventListener(ev, e=>{
  e.preventDefault(); e.stopPropagation(); dropzone.style.background='#f6faff';
}));
dropzone.addEventListener('drop', e=>{
  if (e.dataTransfer.files && e.dataTransfer.files[0]) {
    slipFile.files = e.dataTransfer.files;
    renderSlipPreview();
  }
});
slipFile.addEventListener('change', renderSlipPreview);

function renderSlipPreview(){
  slipPrev.innerHTML = '';
  const f = slipFile.files && slipFile.files[0];
  if (!f) return;
  const ok = /image\/(jpeg|png|webp|heic|heif)/i.test(f.type);
  if (!ok) { alert('ไฟล์ต้องเป็นรูปภาพเท่านั้น'); slipFile.value=''; return; }
  const url = URL.createObjectURL(f);
  const img = new Image(); img.src = url; img.onload = ()=>{
    slipPrev.innerHTML = '';
    img.style.maxWidth = '260px';
    img.style.borderRadius = '10px';
    img.style.border = '1px solid #dce8ff';
    slipPrev.appendChild(img);
    URL.revokeObjectURL(url);
  };
}

frmSlip.addEventListener('submit', async (e)=>{
  e.preventDefault();
  slipMsg.innerHTML = '';
  if (!slipFile.files || !slipFile.files[0]) { alert('กรุณาเลือกไฟล์สลิป'); return; }
  if (slipFile.files[0].size > 5*1024*1024) { alert('ไฟล์เกิน 5MB'); return; }

  btnUpload.disabled = true;

  try{
    const fd = new FormData(frmSlip);
    const res = await fetch('front_store.php', { method:'POST', body:fd, credentials:'same-origin' });
    const j = await res.json();
    if (j && j.ok) {
      slipMsg.innerHTML = '<div class="alert alert-success mb-2">'+ (j.msg||'อัปโหลดสำเร็จ') +'</div>';
      setTimeout(()=>{
        closeSlipModal();
        window.location.href = 'front_store.php?paid=1&oid='+(j.order_id||'');
      }, 1200);
    } else {
      slipMsg.innerHTML = '<div class="alert alert-danger mb-2">'+ (j && j.msg ? j.msg : 'อัปโหลดไม่สำเร็จ') +'</div>';
      btnUpload.disabled = false;
    }
  }catch(err){
    slipMsg.innerHTML = '<div class="alert alert-danger mb-2">เชื่อมต่อไม่สำเร็จ</div>';
    btnUpload.disabled = false;
  }
});

btnCashConfirm?.addEventListener('click', async ()=>{
  const oid = slipOrderId.value;
  if (!oid) return;
  btnCashConfirm.disabled = true;
  cashMsg.innerHTML = '';
  try{
    const fd = new FormData();
    fd.set('action','pay_cash');
    fd.set('order_id', oid);
    const res = await fetch('front_store.php', { method:'POST', body:fd, credentials:'same-origin' });
    const j = await res.json();
    if (j && j.ok) {
      cashMsg.innerHTML = '<div class="alert alert-success">บันทึกชำระเงินสดแล้ว</div>';
      setTimeout(()=>{ closeSlipModal(); window.location.href = 'front_store.php?paid=1&oid='+oid; }, 800);
    } else {
      cashMsg.innerHTML = '<div class="alert alert-danger">'+(j?.msg || 'บันทึกไม่สำเร็จ')+'</div>';
      btnCashConfirm.disabled = false;
    }
  }catch(_){
    cashMsg.innerHTML = '<div class="alert alert-danger">เชื่อมต่อไม่สำเร็จ</div>';
    btnCashConfirm.disabled = false;
  }
});

window.openSlipModal = openSlipModal;
</script>

</body>
</html>

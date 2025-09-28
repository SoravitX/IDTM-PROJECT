<?php
// export_excel.php — Export รายงานยอดขายเป็น Excel (มี KPI: รายได้จากท็อปปิง + ส่วนลดโปรโมชัน + Net After Promo)
// รองรับทั้ง .xlsx (PhpSpreadsheet) และ CSV fallback
declare(strict_types=1);
session_start();
if (empty($_SESSION['uid'])) { header("Location: ../index.php"); exit; }

require __DIR__ . '/../db.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');


  use PhpOffice\PhpSpreadsheet\Spreadsheet;
  use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
  use PhpOffice\PhpSpreadsheet\Style\Fill;
  use PhpOffice\PhpSpreadsheet\Style\Alignment;
  use PhpOffice\PhpSpreadsheet\Style\Border;
  use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
  
// ====== PhpSpreadsheet (autoload ถ้ามี) ======
if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
  $vendor = __DIR__ . '/../vendor/autoload.php';
  if (file_exists($vendor)) { require_once $vendor; }
}
if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
  // use statements จะไม่ error แม้ประกาศไว้ล่วงหน้า

}

function money_fmt($n){ return number_format((float)$n, 2); }
function dt_range_from_period(string $period, string $start='', string $end=''): array {
  $now = new DateTime('now');
  $d0  = (clone $now)->setTime(0,0,0);

  if ($period==='today') {
    $rs = $d0; $re = (clone $rs)->modify('+1 day');
  } elseif ($period==='week') {
    $rs = (clone $d0)->modify('monday this week');
    $re = (clone $rs)->modify('+7 days');
  } elseif ($period==='month') {
    $rs = (clone $d0)->modify('first day of this month');
    $re = (clone $rs)->modify('first day of next month');
  } else {
    $rs = $start ? new DateTime($start.' 00:00:00') : $d0;
    $re = $end   ? new DateTime($end  .' 23:59:59') : (clone $d0)->modify('+1 day');
  }
  return [$rs->format('Y-m-d H:i:s'), $re->format('Y-m-d H:i:s'), $rs, $re];
}

// ==== รับช่วงเวลา ====
$period = $_GET['period'] ?? 'today';
$start  = trim((string)($_GET['start'] ?? ''));
$end    = trim((string)($_GET['end']   ?? ''));
[$rangeStartStr, $rangeEndStr, $rsObj, $reObj] = dt_range_from_period($period, $start, $end);

// สถานะที่นับยอดขายจริง
$OK_STATUSES = ["ready","completed","paid","served"];

// ====== ดึงข้อมูลแบบกลุ่มหมวดหมู่ (เหมือนเดิม) ======
$ph = implode(',', array_fill(0, count($OK_STATUSES), '?'));
$sql = "
  SELECT 
    COALESCE(c.category_name, 'Uncategorized') AS category_name,
    m.menu_id, m.name AS menu_name,
    m.price AS unit_price,
    COALESCE(SUM(od.quantity),0) AS qty,
    COALESCE(SUM(od.total_price),0) AS amount
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  JOIN menu   m ON m.menu_id   = od.menu_id
  LEFT JOIN categories c ON m.category_id = c.category_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($ph)
  GROUP BY COALESCE(c.category_name,'Uncategorized'), m.menu_id, m.name, m.price
  ORDER BY category_name ASC, menu_name ASC
";
$stmt = $conn->prepare($sql);
$types = 'ss' . str_repeat('s', count($OK_STATUSES));
$stmt->bind_param($types, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$res = $stmt->get_result();

$byCat = []; $grandQty = 0; $grandAmount = 0.0;
while ($r = $res->fetch_assoc()) {
  $cat = (string)$r['category_name'];
  if (!isset($byCat[$cat])) $byCat[$cat] = [];
  $byCat[$cat][] = [
    'name'   => $r['menu_name'],
    'qty'    => (int)$r['qty'],
    'price'  => (float)$r['unit_price'],
    'amount' => (float)$r['amount'],
  ];
  $grandQty    += (int)$r['qty'];
  $grandAmount += (float)$r['amount'];
}
$stmt->close();

// ====== (ใหม่) KPI: รายได้จากท็อปปิง + ส่วนลดโปรโมชัน ======
$sqlExtra = "
  SELECT
    COALESCE(SUM(
      GREATEST(
        od.total_price - (
          (m.price - COALESCE(
            CASE
              WHEN p.promo_id IS NULL THEN 0
              WHEN p.discount_type='PERCENT'
                THEN LEAST((p.discount_value/100.0)*m.price, COALESCE(p.max_discount, 999999999))
              ELSE LEAST(p.discount_value, COALESCE(p.max_discount, 999999999))
            END, 0)
          ) * od.quantity
        )
      , 0)
    ), 0) AS topping_total,

    COALESCE(SUM(
      COALESCE(
        CASE
          WHEN p.promo_id IS NULL THEN 0
          WHEN p.discount_type='PERCENT'
            THEN LEAST((p.discount_value/100.0)*m.price, COALESCE(p.max_discount, 999999999))
          ELSE LEAST(p.discount_value, COALESCE(p.max_discount, 999999999))
        END, 0
      ) * od.quantity
    ), 0) AS discount_total
  FROM order_details od
  JOIN orders o ON o.order_id = od.order_id
  JOIN menu   m ON m.menu_id  = od.menu_id
  LEFT JOIN promotions p ON p.promo_id = od.promo_id
  WHERE o.order_time >= ? AND o.order_time < ?
    AND o.status IN ($ph)
";
$stmt = $conn->prepare($sqlExtra);
$stmt->bind_param($types, $rangeStartStr, $rangeEndStr, ...$OK_STATUSES);
$stmt->execute();
$extra = $stmt->get_result()->fetch_assoc() ?: ['topping_total'=>0.0, 'discount_total'=>0.0];
$stmt->close();

$topping_total  = (float)($extra['topping_total']  ?? 0.0);
$discount_total = (float)($extra['discount_total'] ?? 0.0);
$net_after_promo = $grandAmount - $discount_total; // gross - promo

// ====== ถ้ามี PhpSpreadsheet ให้ทำ .xlsx ======
if (class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {

  $wb = new Spreadsheet();
  $ws = $wb->getActiveSheet();
  $ws->setTitle('Sales Report');

  // Title
  $title = "รายงานขายเครื่องดื่ม PSU Blue Café";
  $periodText = "ประจำวันที่ " . $rsObj->format('d M Y') . " ถึง " . $reObj->format('d M Y');
  $ws->setCellValue('E1', $title);
  $ws->setCellValue('E2', $periodText);
  $ws->mergeCells('E1:H1');
  $ws->mergeCells('E2:H2');
  $ws->getStyle('E1')->getFont()->setBold(true)->setSize(14);
  $ws->getStyle('E2')->getFont()->setBold(true);

  // (ใหม่) KPI แสดงด้านบน
  // ใช้คอลัมน์ C-D เป็นกล่อง KPI เล็ก ๆ
  $ws->setCellValue('C1', 'รายได้จากท็อปปิง (THB)');
  $ws->setCellValue('D1', $topping_total);
  $ws->setCellValue('C2', 'ส่วนลดที่ให้ไป (โปรโมชัน) (THB)');
  $ws->setCellValue('D2', $discount_total);
  $ws->setCellValue('C3', 'Net After Promo (THB)');
  $ws->setCellValue('D3', $net_after_promo);

  $ws->getStyle('C1:D3')->getFont()->setBold(true);
  $ws->getStyle('D1:D3')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
  $ws->getStyle('C1:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF6FBFF');
  $ws->getColumnDimension('C')->setWidth(30);
  $ws->getColumnDimension('D')->setWidth(18);

  // Header columns (เหมือนเดิม)
  $ws->setCellValue('E4', 'รายการ');
  $ws->setCellValue('F4', 'จำนวน');
  $ws->setCellValue('G4', 'ราคาต่อหน่วย');
  $ws->setCellValue('H4', 'จำนวนเงิน');
  $ws->getStyle('E4:H4')->getFont()->setBold(true);
  $ws->getStyle('E4:H4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEF5FF');
  $ws->getColumnDimension('E')->setWidth(32);
  $ws->getColumnDimension('F')->setWidth(10);
  $ws->getColumnDimension('G')->setWidth(14);
  $ws->getColumnDimension('H')->setWidth(14);

  // Body by category
  $row = 5;
  foreach ($byCat as $catName => $items) {
    // Category header (shaded)
    $ws->mergeCells("E{$row}:H{$row}");
    $ws->setCellValue("E{$row}", strtoupper($catName));
    $ws->getStyle("E{$row}:H{$row}")->getFont()->setBold(true);
    $ws->getStyle("E{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDDEBFF');
    $row++;

    foreach ($items as $it) {
      $ws->setCellValue("E{$row}", $it['name']);
      $ws->setCellValue("F{$row}", (int)$it['qty']);
      $ws->setCellValue("G{$row}", (float)$it['price']);
      $ws->setCellValue("H{$row}", (float)$it['amount']);
      $row++;
    }
    // spacer line
    $ws->getStyle("E{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF7FAFF');
    $row++;
  }

  // Footer totals
  $ws->setCellValue("G{$row}", 'รวม (Gross)');
  $ws->setCellValue("H{$row}", $grandAmount);
  $ws->getStyle("G{$row}:H{$row}")->getFont()->setBold(true);
  $ws->getStyle("G{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE6F2FF');
  $row++;

  // (ใหม่) แสดง KPI ต่อท้ายตารางด้วย
  $ws->setCellValue("G{$row}", 'รายได้จากท็อปปิง');
  $ws->setCellValue("H{$row}", $topping_total);
  $row++;
  $ws->setCellValue("G{$row}", 'ส่วนลดที่ให้ไป (โปรโมชัน)');
  $ws->setCellValue("H{$row}", $discount_total);
  $row++;
  $ws->setCellValue("G{$row}", 'Net After Promo');
  $ws->setCellValue("H{$row}", $net_after_promo);
  $ws->getStyle("G".($row-2).":H{$row}")->getFont()->setBold(true);

  // Borders & number formats
  $lastDataRow = $row;
  $ws->getStyle("E4:H{$lastDataRow}")
     ->getBorders()->getAllBorders()
     ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFBFD4FF');
  $ws->getStyle("F5:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  $ws->getStyle("G5:H{$lastDataRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

  // Output
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  $fname = 'PSU_Blue_Cafe_Sales_'.date('Ymd_His').'.xlsx';
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  header('Cache-Control: max-age=0');
  $writer = new Xlsx($wb);
  $writer->save('php://output');
  exit;
}

// ====== Fallback: ส่งออก CSV ======
header('Content-Type: text/csv; charset=utf-8');
$fname = 'PSU_Blue_Cafe_Sales_'.date('Ymd_His').'.csv';
header('Content-Disposition: attachment; filename="'.$fname.'"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$out = fopen('php://output', 'w');
// Header text
fputcsv($out, ["รายงานขายเครื่องดื่ม PSU Blue Café"]);
fputcsv($out, ["ช่วงวันที่", $rsObj->format('Y-m-d'), "ถึง", $reObj->format('Y-m-d')]);

// (ใหม่) KPI summary บรรทัดต้น ๆ
fputcsv($out, ['รายได้จากท็อปปิง (THB)', number_format($topping_total, 2, '.', '')]);
fputcsv($out, ['ส่วนลดที่ให้ไป (โปรโมชัน) (THB)', number_format($discount_total, 2, '.', '')]);
fputcsv($out, ['Net After Promo (THB)', number_format($net_after_promo, 2, '.', '')]);
fputcsv($out, []);

// Table header
fputcsv($out, ['หมวดหมู่', 'รายการ', 'จำนวน', 'ราคาต่อหน่วย', 'จำนวนเงิน']);

foreach ($byCat as $catName => $items) {
  foreach ($items as $it) {
    fputcsv($out, [
      $catName,
      $it['name'],
      (int)$it['qty'],
      number_format((float)$it['price'], 2, '.', ''),
      number_format((float)$it['amount'], 2, '.', ''),
    ]);
  }
}

fputcsv($out, []);
fputcsv($out, ['รวมทั้งหมด (Gross)', '', '', '', number_format($grandAmount, 2, '.', '')]);
fputcsv($out, ['รายได้จากท็อปปิง', '', '', '', number_format($topping_total, 2, '.', '')]);
fputcsv($out, ['ส่วนลดที่ให้ไป (โปรโมชัน)', '', '', '', number_format($discount_total, 2, '.', '')]);
fputcsv($out, ['Net After Promo', '', '', '', number_format($net_after_promo, 2, '.', '')]);

fclose($out);
exit;

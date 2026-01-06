<?php
//*********************************************************************//
// AlleyCat PhotoStation : Admin Print Order Processor (Modal/AJAX)
// Author: Paul K. Smith (photos@alleycatphoto.net)
// Date: 10/18/2025
//*********************************************************************//

require_once("config.php");

header('Content-Type: application/json');

$orderID = isset($_POST['order']) ? trim($_POST['order']) : '';
if ($orderID == '') {
    echo json_encode(['status' => 'error', 'message' => 'No order number specified.']);
    exit;
}

$baseDir   = realpath(__DIR__ . "/../photos");
if (!$baseDir) {
    echo json_encode(['status' => 'error', 'message' => 'Photo base directory not found.']);
    exit;
}

$date_path = date('Y/m/d');
$receipt   = "$baseDir/$date_path/receipts/$orderID.txt";
if (!file_exists($receipt)) {
    echo json_encode(['status' => 'error', 'message' => "Receipt not found for Order #$orderID"]);
    exit;
}

$receiptData = file_get_contents($receipt);

// --- Parse Items from receipt text ---
$lines = explode("\n", str_replace("\r", "", $receiptData));
$items = [];

foreach ($lines as $line) {
    if (preg_match('/\[(\d+)\]\s*(.+?)\s*\((\d+)\)/', trim($line), $m)) {
        $qty       = intval($m[1]);
        $item_name = trim($m[2]);
        $photo_id  = trim($m[3]);

        if (preg_match('/(\d+x\d+)/', $item_name, $size)) {
            $prod_code = $size[1];
        } elseif (stripos($item_name, 'email') !== false) {
            $prod_code = 'EML';
        } else {
            $prod_code = 'UNK';
        }

        $items[] = [
            'prod_code' => $prod_code,
            'photo_id'  => $photo_id,
            'quantity'  => $qty
        ];
    }
}
$defaultOutputDir = getenv('PRINT_OUTPUT_DIR') ?: "../orders";
$fsOutputDir      = getenv('PRINT_OUTPUT_DIR_FS') ?: "../orders_fs";

$orderOutputDir = (strpos($receiptData, '- FS') !== false) ? $fsOutputDir : $defaultOutputDir;
if (!is_dir($orderOutputDir)) {
    @mkdir($orderOutputDir, 0777, true);
}

$copiedFiles = [];

foreach ($items as $item) {
    $prod_code = $item['prod_code'];
    $photo_id  = $item['photo_id'];
    $quantity  = $item['quantity'];

    if ($prod_code === 'EML') continue; // skip email-only items

    $sourcefile = "$baseDir/$date_path/raw/$photo_id.jpg";
    if (!file_exists($sourcefile)) continue;

    $orientation = 'V';
    $imgInfo = @getimagesize($sourcefile);
    if ($imgInfo && isset($imgInfo[0], $imgInfo[1])) {
        $orientation = ($imgInfo[0] > $imgInfo[1]) ? 'H' : 'V';
    }

    for ($i = 1; $i <= $quantity; $i++) {
        $destfile = sprintf(
            "%s/%s-%s-%s%s-%d.jpg",
            $orderOutputDir,
            $orderID,
            $photo_id,
            $prod_code,
            $orientation,
            $i
        );
        if (@copy($sourcefile, $destfile)) {
            $copiedFiles[] = basename($destfile);
        }
    }
}

echo json_encode([
    'status' => 'success',
    'message' => "Order #$orderID printed successfully.",
    'files' => $copiedFiles,
    'receipt' => nl2br(htmlspecialchars($receiptData))
]);

<?php
//*********************************************************************//
// AlleyCat PhotoStation : Pending Cash Orders JSON API (for refresh)
//*********************************************************************//
error_log("API HIT: admin_cash_orders_api.php");
require_once("config.php");

header('Content-Type: application/json');

$pendingCashOrders = [];

try {
    // Determine the base path to the photos directory
    $baseDir = realpath(__DIR__ . "/../photos");
    if (!$baseDir) {
        echo json_encode(['status' => 'error', 'message' => 'Photo base directory not found.']);
        exit;
    }

    $date_path   = date('Y/m/d');
    $receiptsDir = rtrim($baseDir, '/').'/'.$date_path.'/receipts';

    if (!is_dir($receiptsDir)) {
        // If the receipts directory doesn't exist, return empty list (status 'ok' expected by JS)
        echo json_encode([
            'status' => 'ok',
            'count'  => 0,
            'orders' => []
        ]);
        exit;
    }

    // Scan all receipt files for today
    $files = glob($receiptsDir.'/*.txt') ?: [];

    foreach ($files as $receiptFile) {
        $raw = @file_get_contents($receiptFile);
        if ($raw === false || trim($raw) === '') {
            continue;
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);

        $isCash = false;
        $amount = 0.0;

        // 1) Look for a CASH ORDER line that ends with DUE (case-insensitive)
        foreach ($lines as $line) {
            $lineTrim = trim($line);
            if (preg_match('/^CASH ORDER:\s*\$([0-9]+(?:\.[0-9]{2})?)\s+DUE\s*$/i', $lineTrim, $m)) {
                $isCash = true;
                $amount = (float)$m[1];
                break;
            }
        }

        if (!$isCash) {
            continue;
        }

        // 2) Extract order details
        $orderId   = null;
        $orderDate = '';
        $label     = '';

        foreach ($lines as $line) {
            $trim = trim($line);

            if ($orderId === null && preg_match('/^Order (Number|#):\s*(\d+)/i', $trim, $m)) {
                $orderId = $m[2];
            }

            if ($orderDate === '' && preg_match('/^Order Date:\s*(.+)$/i', $trim, $m)) {
                $orderDate = trim($m[1]);
            }

            // Find the email/label line (contains '@')
            if ($label === '' && strpos($trim, '@') !== false) {
                $label = $trim;
            }
        }

        if ($orderId === null) {
            $orderId = pathinfo($receiptFile, PATHINFO_FILENAME);
        }

        $pendingCashOrders[] = [
            'id'    => (int)$orderId,
            'name'  => $label,
            'total' => $amount,
            'date'  => $orderDate,
        ];
    }

    // Sort by order ID
    usort($pendingCashOrders, function ($a, $b) {
        return $a['id'] <=> $b['id'];
    });

    // Return status 'ok' as expected by frontend JS
    echo json_encode([
        'status' => 'ok',
        'count'  => count($pendingCashOrders),
        'orders' => $pendingCashOrders,
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
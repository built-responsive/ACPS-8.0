<?php
//*********************************************************************//
// AlleyCat PhotoStation : Cash Order Event Logger
// Handles: Log entry for paid/void/email or reading the log file.
//*********************************************************************//
require_once("config.php");

// Log file path: outside web root for security, or in a dedicated log folder
define('LOG_DIR', realpath(__DIR__ . "/../logs"));
define('LOG_FILE', LOG_DIR . '/cash_orders_event.log');

$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : '';
$logMsg = isset($_POST['log_message']) ? trim($_POST['log_message']) : '';

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0777, true);
}

// ---------------------------------------------------------------------
// ACTION: VIEW (Used by frontend log modal)
// ---------------------------------------------------------------------
if ($action === 'view') {
    header('Content-Type: text/plain');
    if (file_exists(LOG_FILE)) {
        // Read the last N lines (e.g., last 200 lines) for performance
        $lines = @file(LOG_FILE, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            $lines = array_slice($lines, -200); // Only show last 200 lines
            echo implode("\n", $lines);
        } else {
            echo "Error: Unable to read log file.";
        }
    } else {
        echo "Log file not found.";
    }
    exit;
}

// ---------------------------------------------------------------------
// ACTION: LOG (Used by admin_cash_order_action.php POST requests)
// ---------------------------------------------------------------------
if ($action === 'log' && $logMsg !== '') {
    // Security: Only allow logging via POST data and internal action trigger
    $logMsg = str_replace(array("\r", "\n"), '', $logMsg); // Single line per entry
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "{$timestamp} | " . $logMsg . "\n";

    // Append to log file
    if (@file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX) !== false) {
        echo json_encode(['status' => 'success', 'message' => 'Log entry recorded.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to write to log file.']);
    }
    exit;
}

// If no valid action is given
header('Content-Type: application/json');
http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid or missing action.']);
<?php
//*********************************************************************//
// AlleyCat PhotoStation - Square Terminal Integration
//*********************************************************************//

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

use Square\SquareClient;
use Square\Environments;
use Square\Terminal\Checkouts\Requests\CreateTerminalCheckoutRequest;
use Square\Types\TerminalCheckout;
use Square\Types\Money;
use Square\Types\Currency;
use Square\Types\DeviceCheckoutOptions;

header('Content-Type: application/json');

// --- Configuration ---
// Using provided credentials from instruction, but ideally these should be in .env
$accessToken = 'EAAAl2xOldKvyJoWa_B6V_EzAR8JNJelKHxVIpmxWaKm-RVONhwEvcklomg8fCip';
$deviceId    = '521CS149B9003337'; 
$environment = Environments::Production; 

$client = new SquareClient(
    token: $accessToken,
    options: [
        'baseUrl' => $environment->value,
    ],
);

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create') {
        $orderId = $_POST['order_id'] ?? '';
        $amount  = floatval($_POST['amount'] ?? 0);

        if (empty($orderId) || $amount <= 0) {
            throw new Exception("Invalid order ID or amount.");
        }

        // Convert amount to cents
        $amountCents = (int)round($amount * 100);
        $idempotencyKey = uniqid('term_', true);

        $request = new CreateTerminalCheckoutRequest([
            'idempotencyKey' => $idempotencyKey,
            'checkout' => new TerminalCheckout([
                'amountMoney' => new Money([
                    'amount' => $amountCents,
                    'currency' => Currency::Usd->value,
                ]),
                'deviceOptions' => new DeviceCheckoutOptions([
                    'deviceId' => $deviceId,
                ]),
                'referenceId' => $orderId, 
                'note' => "Order #$orderId",
            ]),
        ]);

        $response = $client->terminal->checkouts->create($request);

        if ($response->getErrors()) {
            throw new Exception("API Error: " . json_encode($response->getErrors()));
        }

        $checkout = $response->getResult()->getCheckout();
        
        echo json_encode([
            'status' => 'success',
            'checkout_id' => $checkout->getId(),
            'terminal_status' => $checkout->getStatus(),
        ]);

    } elseif ($action === 'poll') {
        $checkoutId = $_POST['checkout_id'] ?? '';

        if (empty($checkoutId)) {
            throw new Exception("Missing checkout ID.");
        }

        $response = $client->terminal->checkouts->get($checkoutId);

        if ($response->getErrors()) {
            throw new Exception("API Error: " . json_encode($response->getErrors()));
        }

        $checkout = $response->getResult()->getCheckout();
        $status = $checkout->getStatus(); // PENDING, IN_PROGRESS, CANCEL_REQUESTED, CANCELED, COMPLETED

        echo json_encode([
            'status' => 'success',
            'terminal_status' => $status,
        ]);

    } elseif ($action === 'cancel') {
        $checkoutId = $_POST['checkout_id'] ?? '';
        if (empty($checkoutId)) throw new Exception("Missing checkout ID.");

        $response = $client->terminal->checkouts->cancel($checkoutId);
        if ($response->getErrors()) {
            throw new Exception("API Error: " . json_encode($response->getErrors()));
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Cancellation requested.']);

    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

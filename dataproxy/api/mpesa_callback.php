<?php
// =========================================================
// api/mpesa_callback.php — Daraja hits this URL after STK push
// completes (success or failure). No auth — verified by
// matching CheckoutRequestID against our own pending records.
// =========================================================
require_once __DIR__ . '/../includes/mpesa.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// Log every callback for debugging/audit, even malformed ones
file_put_contents(__DIR__ . '/../logs/mpesa_callbacks.log', date('c') . ' ' . $raw . PHP_EOL, FILE_APPEND);

$result = mpesaParseCallback($data ?? []);
$pdo = getDB();

if (empty($result['checkout_request_id'])) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Malformed callback']);
    exit;
}

$checkoutId = $result['checkout_request_id'];

// ---- Case 1: matches a wallet top-up ----
$stmt = $pdo->prepare("SELECT * FROM wallet_transactions WHERE mpesa_ref = ? AND status = 'pending'");
$stmt->execute([$checkoutId]);
$walletTxn = $stmt->fetch();

if ($walletTxn) {
    if ($result['success']) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE wallet_transactions SET status = 'completed', mpesa_ref = ? WHERE id = ?")
            ->execute([$result['mpesa_receipt'], $walletTxn['id']]);
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
            ->execute([$walletTxn['amount'], $walletTxn['user_id']]);
        $pdo->commit();

        $u = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $u->execute([$walletTxn['user_id']]);
        $phone = $u->fetchColumn();
        if ($phone) {
            sendSms($phone, "Wallet top-up of KSh {$walletTxn['amount']} successful. Ref: {$result['mpesa_receipt']}", 'topup');
        }
    } else {
        $pdo->prepare("UPDATE wallet_transactions SET status = 'failed', notes = ? WHERE id = ?")
            ->execute([$result['error'], $walletTxn['id']]);
    }

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// ---- Case 2: matches a guest direct bundle payment ----
$stmt = $pdo->prepare("SELECT * FROM data_orders WHERE mpesa_ref = ? AND status = 'pending'");
$stmt->execute([$checkoutId]);
$order = $stmt->fetch();

if ($order) {
    if ($result['success']) {
        $pdo->prepare("UPDATE data_orders SET status = 'paid', mpesa_ref = ? WHERE id = ?")
            ->execute([$result['mpesa_receipt'], $order['id']]);

        require_once __DIR__ . '/../includes/bundle_delivery.php';
        deliverBundle($order['id']);

        $phone = $order['guest_phone'] ?: $order['recipient_phone'];
        sendSms($phone, "Payment received. Your bundle order {$order['guest_order_ref']} is being processed.", 'purchase');
    } else {
        $pdo->prepare("UPDATE data_orders SET status = 'failed' WHERE id = ?")
            ->execute([$order['id']]);
    }

    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// No match found — still acknowledge to Daraja so it stops retrying
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted, no matching record']);

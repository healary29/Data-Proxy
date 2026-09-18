<?php
// =========================================================
// api/wallet_topup.php — logged-in users top up wallet via M-Pesa
// POST: amount
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mpesa.php';

header('Content-Type: application/json');

$user = requireLoginJson();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$amount = (float) ($input['amount'] ?? 0);

if ($amount < 10) {
    echo json_encode(['success' => false, 'error' => 'Minimum top-up is KSh 10']);
    exit;
}

$pdo = getDB();

// Create a pending wallet_transactions row first
$stmt = $pdo->prepare(
    "INSERT INTO wallet_transactions (user_id, type, amount, status) VALUES (?, 'topup', ?, 'pending')"
);
$stmt->execute([$user['id'], $amount]);
$txnId = $pdo->lastInsertId();

$result = mpesaStkPush(
    $user['phone'],
    $amount,
    'WALLET-' . $txnId,
    'Wallet top-up'
);

if (!$result['success']) {
    $pdo->prepare("UPDATE wallet_transactions SET status = 'failed', notes = ? WHERE id = ?")
        ->execute([$result['error'], $txnId]);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

// Store checkout_request_id so the callback can match it back to this txn
$pdo->prepare("UPDATE wallet_transactions SET mpesa_ref = ? WHERE id = ?")
    ->execute([$result['checkout_request_id'], $txnId]);

echo json_encode([
    'success' => true,
    'message' => 'Enter your M-Pesa PIN on your phone to complete the top-up',
    'transaction_id' => $txnId,
]);

<?php
// =========================================================
// api/mpesa_b2c_callback.php — Daraja hits this after a B2C
// payout completes or fails (async, separate from the
// synchronous request in mpesa_b2c.php).
// =========================================================
require_once __DIR__ . '/../includes/mpesa_b2c.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
file_put_contents(__DIR__ . '/../logs/mpesa_b2c_callbacks.log', date('c') . ' ' . $raw . PHP_EOL, FILE_APPEND);

$data = json_decode($raw, true);
$result = b2cParseResultCallback($data ?? []);
$pdo = getDB();

if (empty($result['conversation_id'])) {
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Malformed callback']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM payouts WHERE mpesa_ref = ? AND status IN ('pending','paid')");
$stmt->execute([$result['conversation_id']]);
$payout = $stmt->fetch();

if ($payout) {
    if ($result['success']) {
        $pdo->prepare("UPDATE payouts SET status = 'paid' WHERE id = ?")->execute([$payout['id']]);

        $u = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $u->execute([$payout['device_owner_id']]);
        $phone = $u->fetchColumn();
        if ($phone) {
            sendSms($phone, "Payout of KSh {$payout['amount']} has been sent to your M-Pesa.", 'payout');
        }
    } else {
        $pdo->prepare("UPDATE payouts SET status = 'rejected' WHERE id = ?")->execute([$payout['id']]);
    }
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);

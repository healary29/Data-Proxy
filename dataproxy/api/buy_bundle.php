<?php
// =========================================================
// api/buy_bundle.php — purchase a data bundle
// Supports two flows:
//   1. Guest checkout: phone + bundle_id -> STK push directly, no login
//   2. Logged-in: pay from wallet_balance instantly (no STK push needed)
// POST: bundle_id, recipient_phone, (guest_phone if not logged in)
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mpesa.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$bundleId = (int) ($input['bundle_id'] ?? 0);
$recipientPhone = trim($input['recipient_phone'] ?? '');

if (!$bundleId || !$recipientPhone) {
    echo json_encode(['success' => false, 'error' => 'Bundle and recipient phone are required']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM data_bundles WHERE id = ? AND is_active = 1");
$stmt->execute([$bundleId]);
$bundle = $stmt->fetch();

if (!$bundle) {
    echo json_encode(['success' => false, 'error' => 'Bundle not found or unavailable']);
    exit;
}

$user = currentUser(); // null if not logged in
$recipientPhone = normalizePhone($recipientPhone);

// ---------------------------------------------------------
// Flow 1: Logged-in user paying from wallet
// ---------------------------------------------------------
if ($user) {
    if ($user['wallet_balance'] < $bundle['sell_price']) {
        echo json_encode([
            'success' => false,
            'error' => 'Insufficient wallet balance. Please top up first.',
            'shortfall' => $bundle['sell_price'] - $user['wallet_balance'],
        ]);
        exit;
    }

    $pdo->beginTransaction();

    $orderRef = generateGuestOrderRef(); // still useful as a universal order ref
    $stmt = $pdo->prepare(
        "INSERT INTO data_orders (user_id, guest_order_ref, bundle_id, recipient_phone, payment_method, status)
         VALUES (?, ?, ?, ?, 'wallet', 'paid')"
    );
    $stmt->execute([$user['id'], $orderRef, $bundleId, $recipientPhone]);
    $orderId = $pdo->lastInsertId();

    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
        ->execute([$bundle['sell_price'], $user['id']]);

    $pdo->prepare(
        "INSERT INTO wallet_transactions (user_id, type, amount, status, notes) VALUES (?, 'purchase', ?, 'completed', ?)"
    )->execute([$user['id'], $bundle['sell_price'], "Bundle order $orderRef"]);

    $pdo->commit();

    // Attempt delivery (manual mode by default — flags for admin, no external call)
    require_once __DIR__ . '/../includes/bundle_delivery.php';
    deliverBundle($orderId);

    sendSms($recipientPhone, "Order $orderRef: {$bundle['name']} bundle is being processed.", 'purchase');

    echo json_encode(['success' => true, 'order_ref' => $orderRef, 'order_id' => $orderId, 'paid_from' => 'wallet']);
    exit;
}

// ---------------------------------------------------------
// Flow 2: Guest checkout — pay directly via STK push
// ---------------------------------------------------------
$guestPhone = trim($input['guest_phone'] ?? '');
if (!$guestPhone) {
    echo json_encode(['success' => false, 'error' => 'Phone number is required for guest checkout']);
    exit;
}
$guestPhone = normalizePhone($guestPhone);
$orderRef = generateGuestOrderRef();

$stmt = $pdo->prepare(
    "INSERT INTO data_orders (guest_phone, guest_order_ref, bundle_id, recipient_phone, payment_method, status)
     VALUES (?, ?, ?, ?, 'mpesa_direct', 'pending')"
);
$stmt->execute([$guestPhone, $orderRef, $bundleId, $recipientPhone]);
$orderId = $pdo->lastInsertId();

$result = mpesaStkPush($guestPhone, $bundle['sell_price'], $orderRef, "{$bundle['name']} bundle");

if (!$result['success']) {
    $pdo->prepare("UPDATE data_orders SET status = 'failed' WHERE id = ?")->execute([$orderId]);
    echo json_encode(['success' => false, 'error' => $result['error']]);
    exit;
}

$pdo->prepare("UPDATE data_orders SET mpesa_ref = ? WHERE id = ?")
    ->execute([$result['checkout_request_id'], $orderId]);

echo json_encode([
    'success' => true,
    'message' => 'Enter your M-Pesa PIN to complete payment',
    'order_ref' => $orderRef,
    'order_id' => $orderId,
    'note' => 'Save this order reference — you\'ll need it to track your order or raise a complaint',
]);

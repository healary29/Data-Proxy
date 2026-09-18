<?php
// =========================================================
// api/raise_ticket.php — create a support ticket
// Logged-in: user_id from session, order_id optional
// Guest: must pass guest_phone + guest_order_ref that match
//        a real data_orders row (verified via verifyGuestOrder)
// POST: order_type, subject, message, order_id (optional),
//       guest_phone (guest only), guest_order_ref (guest only)
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$orderType = $input['order_type'] ?? 'other';
$subject = trim($input['subject'] ?? '');
$message = trim($input['message'] ?? '');
$orderId = !empty($input['order_id']) ? (int) $input['order_id'] : null;

$validTypes = ['data', 'proxy', 'wallet', 'other'];
if (!in_array($orderType, $validTypes, true)) {
    $orderType = 'other';
}

if (!$subject || !$message) {
    echo json_encode(['success' => false, 'error' => 'Subject and message are required']);
    exit;
}

$pdo = getDB();
$user = currentUser();

if ($user) {
    // ---- Logged-in flow ----
    $stmt = $pdo->prepare(
        "INSERT INTO tickets (user_id, order_type, order_id, subject, status, priority)
         VALUES (?, ?, ?, ?, 'open', 'normal')"
    );
    $stmt->execute([$user['id'], $orderType, $orderId, $subject]);
    $ticketId = $pdo->lastInsertId();

} else {
    // ---- Guest flow — must verify ownership first ----
    $guestPhone = trim($input['guest_phone'] ?? '');
    $guestOrderRef = trim($input['guest_order_ref'] ?? '');

    if (!$guestPhone || !$guestOrderRef) {
        echo json_encode(['success' => false, 'error' => 'Guest phone and order reference are required']);
        exit;
    }

    $order = verifyGuestOrder($guestPhone, strtoupper($guestOrderRef));
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Could not verify this order. Check your phone number and order reference.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO tickets (guest_phone, guest_order_ref, order_type, order_id, subject, status, priority)
         VALUES (?, ?, 'data', ?, ?, 'open', 'normal')"
    );
    $stmt->execute([normalizePhone($guestPhone), strtoupper($guestOrderRef), $order['id'], $subject]);
    $ticketId = $pdo->lastInsertId();
}

// First message goes into ticket_replies as the opening message from the user
$pdo->prepare(
    "INSERT INTO ticket_replies (ticket_id, sender, message) VALUES (?, 'user', ?)"
)->execute([$ticketId, $message]);

// Notify recipient phone so they know the ticket was received
$notifyPhone = $user['phone'] ?? ($guestPhone ?? null);
if ($notifyPhone) {
    sendSms($notifyPhone, "Your support ticket #$ticketId has been received. We'll get back to you shortly.", 'ticket');
}

echo json_encode(['success' => true, 'ticket_id' => $ticketId]);

<?php
// =========================================================
// api/guest_order_lookup.php — verify a guest owns an order
// before letting them raise or view a ticket against it.
// POST: guest_phone, order_ref
// =========================================================
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$phone = trim($input['guest_phone'] ?? '');
$orderRef = trim($input['order_ref'] ?? '');

if (!$phone || !$orderRef) {
    echo json_encode(['success' => false, 'error' => 'Phone number and order reference are required']);
    exit;
}

$order = verifyGuestOrder($phone, strtoupper($orderRef));

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'No matching order found. Check your phone number and order reference.']);
    exit;
}

$pdo = getDB();

// Pull bundle name for a nicer confirmation display
$stmt = $pdo->prepare("SELECT name FROM data_bundles WHERE id = ?");
$stmt->execute([$order['bundle_id']]);
$bundleName = $stmt->fetchColumn();

// Any existing tickets already raised against this order
$stmt = $pdo->prepare(
    "SELECT id, subject, status, created_at FROM tickets
     WHERE guest_phone = ? AND guest_order_ref = ? ORDER BY created_at DESC"
);
$stmt->execute([normalizePhone($phone), strtoupper($orderRef)]);
$existingTickets = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'order' => [
        'order_ref' => $order['guest_order_ref'],
        'bundle_name' => $bundleName,
        'recipient_phone' => $order['recipient_phone'],
        'status' => $order['status'],
        'created_at' => $order['created_at'],
    ],
    'existing_tickets' => $existingTickets,
]);

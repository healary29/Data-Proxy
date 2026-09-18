<?php
// =========================================================
// api/ticket_reply.php — two actions:
//   GET  ?ticket_id=X[&guest_phone=&guest_order_ref=]  -> fetch thread
//   POST { ticket_id, message, guest_phone?, guest_order_ref? } -> reply
// Ownership check applies to both logged-in users and guests.
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';

header('Content-Type: application/json');

$pdo = getDB();
$user = currentUser();

function loadTicketForRequester(PDO $pdo, ?array $user, int $ticketId, string $guestPhone = '', string $guestOrderRef = ''): ?array {
    $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = ?");
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch();
    if (!$ticket) return null;

    if ($user) {
        return ($ticket['user_id'] == $user['id']) ? $ticket : null;
    }

    // Guest — must match phone + order ref on the ticket itself
    if (!$guestPhone || !$guestOrderRef) return null;
    $phone = normalizePhone($guestPhone);
    if ($ticket['guest_phone'] === $phone && $ticket['guest_order_ref'] === strtoupper($guestOrderRef)) {
        return $ticket;
    }
    return null;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $ticketId = (int) ($_GET['ticket_id'] ?? 0);
    $guestPhone = $_GET['guest_phone'] ?? '';
    $guestOrderRef = $_GET['guest_order_ref'] ?? '';

    $ticket = loadTicketForRequester($pdo, $user, $ticketId, $guestPhone, $guestOrderRef);
    if (!$ticket) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ticket not found or access denied']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT sender, message, created_at FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticketId]);
    $replies = $stmt->fetchAll();

    echo json_encode(['success' => true, 'ticket' => $ticket, 'replies' => $replies]);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $ticketId = (int) ($input['ticket_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    $guestPhone = $input['guest_phone'] ?? '';
    $guestOrderRef = $input['guest_order_ref'] ?? '';

    if (!$ticketId || !$message) {
        echo json_encode(['success' => false, 'error' => 'Ticket ID and message are required']);
        exit;
    }

    $ticket = loadTicketForRequester($pdo, $user, $ticketId, $guestPhone, $guestOrderRef);
    if (!$ticket) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Ticket not found or access denied']);
        exit;
    }

    if (in_array($ticket['status'], ['resolved', 'closed'], true)) {
        // Reopen if the user replies to a resolved/closed ticket
        $pdo->prepare("UPDATE tickets SET status = 'open' WHERE id = ?")->execute([$ticketId]);
    }

    $pdo->prepare("INSERT INTO ticket_replies (ticket_id, sender, message) VALUES (?, 'user', ?)")
        ->execute([$ticketId, $message]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

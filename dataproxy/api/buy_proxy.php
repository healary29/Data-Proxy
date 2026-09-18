<?php
// =========================================================
// api/buy_proxy.php — purchase a proxy plan
// Logged-in users only, paid from wallet_balance.
// POST: plan_id
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sms.php';
require_once __DIR__ . '/../includes/device_cleanup.php';

header('Content-Type: application/json');

$user = requireLoginJson();

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$planId = (int) ($input['plan_id'] ?? 0);

if (!$planId) {
    echo json_encode(['success' => false, 'error' => 'Plan is required']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM proxy_plans WHERE id = ? AND is_active = 1");
$stmt->execute([$planId]);
$plan = $stmt->fetch();

if (!$plan) {
    echo json_encode(['success' => false, 'error' => 'Plan not found or unavailable']);
    exit;
}

if ($user['wallet_balance'] < $plan['price']) {
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient wallet balance. Please top up first.',
        'shortfall' => $plan['price'] - $user['wallet_balance'],
    ]);
    exit;
}

// Keep device statuses honest before picking one (no cron needed)
markStaleDevicesOffline();

// ---------------------------------------------------------
// Find an available online device to assign as exit node
// ---------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT * FROM devices WHERE status = 'online' ORDER BY RAND() LIMIT 1"
);
$stmt->execute();
$device = $stmt->fetch();

if (!$device) {
    echo json_encode(['success' => false, 'error' => 'No proxy capacity available right now. Please try again shortly.']);
    exit;
}

$pdo->beginTransaction();

// Generate credentials for this proxy order
$proxyUsername = 'u' . bin2hex(random_bytes(4));
$proxyPassword = bin2hex(random_bytes(6));
$expiresAt = date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days"));

$stmt = $pdo->prepare(
    "INSERT INTO proxy_orders (user_id, plan_id, proxy_host, proxy_port, proxy_username, proxy_password, status, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, 'active', ?)"
);
$stmt->execute([
    $user['id'], $planId, $device['exit_ip'], 8080, $proxyUsername, $proxyPassword, $expiresAt,
]);
$orderId = $pdo->lastInsertId();

$pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
    ->execute([$plan['price'], $user['id']]);

$pdo->prepare(
    "INSERT INTO wallet_transactions (user_id, type, amount, status, notes) VALUES (?, 'purchase', ?, 'completed', ?)"
)->execute([$user['id'], $plan['price'], "Proxy plan: {$plan['name']}"]);

$pdo->commit();

sendSms(
    $user['phone'],
    "Proxy plan '{$plan['name']}' activated. Valid until $expiresAt. Check your dashboard for credentials.",
    'purchase'
);

echo json_encode([
    'success' => true,
    'order_id' => $orderId,
    'proxy' => [
        'host' => $device['exit_ip'],
        'port' => 8080,
        'username' => $proxyUsername,
        'password' => $proxyPassword,
        'expires_at' => $expiresAt,
    ],
]);

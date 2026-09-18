<?php
// =========================================================
// api/device_heartbeat.php — participant device check-in
//
// Called by the participant-side app/script (running on the
// phone/PC acting as a proxy exit node) periodically, e.g.
// every 60 seconds, to register itself and report it's alive.
//
// POST: device_label (optional, first call only),
//       device_info (optional, e.g. OS/app version),
//       exit_ip (the device's current public IP)
// Requires the participant to be logged in (session cookie),
// OR pass user_id + a simple shared device_token if you build
// a headless client later — see note below.
// =========================================================
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$user = currentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in first']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$deviceId = !empty($input['device_id']) ? (int) $input['device_id'] : null;
$deviceLabel = trim($input['device_label'] ?? '');
$deviceInfo = trim($input['device_info'] ?? '');
$exitIp = trim($input['exit_ip'] ?? '') ?: ($_SERVER['REMOTE_ADDR'] ?? null);

$pdo = getDB();

if ($deviceId) {
    // Existing device checking in again — just update heartbeat
    $stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ? AND participant_user_id = ?");
    $stmt->execute([$deviceId, $user['id']]);
    $device = $stmt->fetch();

    if (!$device) {
        echo json_encode(['success' => false, 'error' => 'Device not found for this account']);
        exit;
    }

    $pdo->prepare("UPDATE devices SET status = 'online', exit_ip = ?, last_seen = NOW() WHERE id = ?")
        ->execute([$exitIp, $deviceId]);

    echo json_encode(['success' => true, 'device_id' => $deviceId]);
    exit;
}

// First check-in — register a new device
$stmt = $pdo->prepare(
    "INSERT INTO devices (participant_user_id, device_label, device_info, exit_ip, status, last_seen)
     VALUES (?, ?, ?, ?, 'online', NOW())"
);
$stmt->execute([$user['id'], $deviceLabel ?: null, $deviceInfo ?: null, $exitIp]);
$newDeviceId = $pdo->lastInsertId();

echo json_encode([
    'success' => true,
    'device_id' => $newDeviceId,
    'message' => 'Device registered. Send device_id on future heartbeats to keep it marked online.',
]);

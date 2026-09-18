<?php
// =========================================================
// device_cleanup.php — flip stale devices to 'offline'
//
// No heartbeat in DEVICE_STALE_MINUTES -> considered offline.
// Called opportunistically from anywhere that reads device
// status (buy_proxy.php's device picker, admin/devices.php),
// so the DB stays honest without needing a real cron job set
// up on shared/local hosting. Cheap — a single UPDATE.
// =========================================================
require_once __DIR__ . '/db.php';

if (!defined('DEVICE_STALE_MINUTES')) {
    define('DEVICE_STALE_MINUTES', 3); // no heartbeat in 3 min = offline
}

function markStaleDevicesOffline(): void {
    $pdo = getDB();
    $pdo->prepare("
        UPDATE devices
        SET status = 'offline'
        WHERE status = 'online'
          AND (last_seen IS NULL OR last_seen < DATE_SUB(NOW(), INTERVAL ? MINUTE))
    ")->execute([DEVICE_STALE_MINUTES]);
}

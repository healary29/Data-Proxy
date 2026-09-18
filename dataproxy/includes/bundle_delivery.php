<?php
// =========================================================
// bundle_delivery.php — deliver a purchased data bundle
//
// Three delivery modes, controlled by BUNDLE_DELIVERY_MODE in config.php:
//   'api'    — call a configured aggregator/dealer API (fill in the
//              request shape once you have real credentials + docs)
//   'manual' — do nothing automatically; notify admin via SMS so they
//              can push the bundle manually and mark it delivered
//              from the admin Orders page. This is the safe default.
//
// Call deliverBundle($orderId) right after a data_orders row is
// marked 'paid' (from buy_bundle.php wallet flow, or from the
// M-Pesa callback for guest direct payments).
// =========================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sms.php';

function deliverBundle(int $orderId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("
        SELECT do.*, db.name AS bundle_name, db.size_mb
        FROM data_orders do JOIN data_bundles db ON db.id = do.bundle_id
        WHERE do.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'error' => 'Order not found'];
    }
    if ($order['status'] !== 'paid') {
        return ['success' => false, 'error' => 'Order is not in a paid state, refusing to deliver'];
    }

    $mode = defined('BUNDLE_DELIVERY_MODE') ? BUNDLE_DELIVERY_MODE : 'manual';

    if ($mode === 'api') {
        $result = deliverBundleViaApi($order);
        if ($result['success']) {
            $pdo->prepare("UPDATE data_orders SET status = 'delivered' WHERE id = ?")->execute([$orderId]);
            sendSms($order['recipient_phone'], "Your {$order['bundle_name']} bundle has been delivered. Enjoy!", 'delivery');
            return ['success' => true, 'mode' => 'api'];
        }
        // API failed — fall through to manual so the order isn't silently stuck
        notifyAdminManualDelivery($order, $result['error'] ?? 'Aggregator API failed');
        return ['success' => false, 'error' => $result['error'] ?? 'Delivery API failed, flagged for manual delivery'];
    }

    // Default: manual mode — flag for admin, leave status as 'paid'
    // until admin marks it 'delivered' from the Orders page.
    notifyAdminManualDelivery($order);
    return ['success' => true, 'mode' => 'manual_pending'];
}

// ---------------------------------------------------------
// Aggregator/dealer API call — FILL THIS IN once you have
// real API docs and credentials. This is a placeholder shape.
// ---------------------------------------------------------
function deliverBundleViaApi(array $order): array {
    if (!defined('BUNDLE_API_URL') || !defined('BUNDLE_API_KEY') || !BUNDLE_API_URL) {
        return ['success' => false, 'error' => 'Bundle API not configured'];
    }

    $payload = [
        'recipient_phone' => $order['recipient_phone'],
        'size_mb' => $order['size_mb'],
        'reference' => $order['guest_order_ref'],
    ];

    $ch = curl_init(BUNDLE_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . BUNDLE_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => "Aggregator request failed: $err"];
    }

    $data = json_decode($response, true);
    // NOTE: adjust this success check to match the real aggregator's response shape
    if (!empty($data['success']) || !empty($data['status']) && $data['status'] === 'ok') {
        return ['success' => true];
    }

    return ['success' => false, 'error' => $data['message'] ?? 'Aggregator reported failure'];
}

// ---------------------------------------------------------
// Notify admin (by SMS to an ops number) that an order needs
// manual delivery. Logs to bundle_delivery_queue table too so
// it shows up reliably even if SMS fails.
// ---------------------------------------------------------
function notifyAdminManualDelivery(array $order, ?string $reason = null): void {
    $pdo = getDB();
    $pdo->prepare("
        INSERT INTO bundle_delivery_queue (order_id, reason, status)
        VALUES (?, ?, 'pending')
        ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = 'pending'
    ")->execute([$order['id'], $reason]);

    if (defined('ADMIN_OPS_PHONE') && ADMIN_OPS_PHONE) {
        sendSms(
            ADMIN_OPS_PHONE,
            "New bundle to deliver manually: {$order['bundle_name']} to {$order['recipient_phone']} (Ref: {$order['guest_order_ref']})",
            'admin_alert'
        );
    }
}

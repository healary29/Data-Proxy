<?php
// =========================================================
// sms.php — Africa's Talking SMS wrapper
// =========================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function sendSms(string $phone, string $message, string $type = 'general'): bool {
    $url = 'https://api.africastalking.com/version1/messaging';
    $phone = normalizePhoneForSms($phone);

    $fields = [
        'username' => AT_USERNAME,
        'to'       => $phone,
        'message'  => $message,
    ];
    if (AT_SENDER_ID) {
        $fields['from'] = AT_SENDER_ID;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => [
            'apiKey: ' . AT_API_KEY,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    $status = $err ? 'failed' : 'sent';

    $pdo = getDB();
    $pdo->prepare(
        "INSERT INTO sms_logs (recipient, message, type, status) VALUES (?, ?, ?, ?)"
    )->execute([$phone, $message, $type, $status]);

    return $status === 'sent';
}

function normalizePhoneForSms(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
        $phone = '254' . $phone;
    }
    return '+' . $phone;
}

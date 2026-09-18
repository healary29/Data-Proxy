<?php
// =========================================================
// mpesa_b2c.php — Daraja B2C (Business to Customer) payouts
//
// This is a SEPARATE Daraja product from STK Push (mpesa.php).
// You must apply for and get approved for B2C separately in the
// Safaricom developer portal before MPESA_B2C_ENABLED can be true.
//
// While MPESA_B2C_ENABLED is false, triggerB2cPayout() returns a
// clear "not configured" error instead of silently pretending to
// succeed — callers (admin/payouts.php) fall back to manual marking.
// =========================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mpesa.php'; // reuses mpesaBaseUrl() and mpesaGetAccessToken()

function triggerB2cPayout(string $phone, float $amount, string $remarks, string $occasion = 'Payout'): array {
    if (!defined('MPESA_B2C_ENABLED') || !MPESA_B2C_ENABLED) {
        return ['success' => false, 'error' => 'B2C payouts not configured yet — mark this payout manually once you\'ve sent it another way'];
    }

    $token = mpesaGetAccessToken();
    if (!$token) {
        return ['success' => false, 'error' => 'Could not authenticate with M-Pesa'];
    }

    $phone = normalizePhoneForMpesa($phone);

    $payload = [
        'InitiatorName'      => MPESA_B2C_INITIATOR_NAME,
        'SecurityCredential' => MPESA_B2C_SECURITY_CREDENTIAL,
        'CommandID'          => 'BusinessPayment',
        'Amount'             => (int) round($amount),
        'PartyA'             => MPESA_B2C_SHORTCODE,
        'PartyB'             => $phone,
        'Remarks'            => $remarks,
        'QueueTimeOutURL'    => MPESA_B2C_TIMEOUT_URL,
        'ResultURL'          => MPESA_B2C_RESULT_URL,
        'Occasion'           => $occasion,
    ];

    $ch = curl_init(mpesaBaseUrl() . '/mpesa/b2c/v1/paymentrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $token",
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => 'B2C request failed: ' . $err];
    }

    $data = json_decode($response, true);

    if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
        return [
            'success' => true,
            'conversation_id' => $data['ConversationID'] ?? null,
            'originator_conversation_id' => $data['OriginatorConversationID'] ?? null,
        ];
    }

    return ['success' => false, 'error' => $data['errorMessage'] ?? 'B2C payout request rejected'];
}

// ---------------------------------------------------------
// Parse the async B2C result callback (Daraja hits ResultURL
// once the payout actually completes or fails)
// ---------------------------------------------------------
function b2cParseResultCallback(array $callbackData): array {
    $result = $callbackData['Result'] ?? null;
    if (!$result) {
        return ['success' => false, 'error' => 'Malformed B2C callback'];
    }

    if (($result['ResultCode'] ?? -1) !== 0) {
        return [
            'success' => false,
            'conversation_id' => $result['ConversationID'] ?? null,
            'error' => $result['ResultDesc'] ?? 'Payout failed',
        ];
    }

    $params = [];
    foreach ($result['ResultParameters']['ResultParameter'] ?? [] as $p) {
        $params[$p['Key']] = $p['Value'] ?? null;
    }

    return [
        'success' => true,
        'conversation_id' => $result['ConversationID'] ?? null,
        'transaction_id' => $result['TransactionID'] ?? null,
        'amount' => $params['TransactionAmount'] ?? null,
        'receiver' => $params['ReceiverPartyPublicName'] ?? null,
    ];
}

<?php
// =========================================================
// mpesa.php — Daraja STK Push (Lipa Na M-Pesa Online)
// =========================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function mpesaBaseUrl(): string {
    return MPESA_ENV === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

// ---------------------------------------------------------
// Get OAuth access token
// ---------------------------------------------------------
function mpesaGetAccessToken(): ?string {
    $url = mpesaBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Basic $credentials"],
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return null;
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// ---------------------------------------------------------
// Trigger STK Push to a customer's phone
// $accountRef: short string shown on the customer's phone (e.g. order ref)
// $description: shown as transaction description
// ---------------------------------------------------------
function mpesaStkPush(string $phone, float $amount, string $accountRef, string $description): array {
    $token = mpesaGetAccessToken();
    if (!$token) {
        return ['success' => false, 'error' => 'Could not authenticate with M-Pesa'];
    }

    $phone = normalizePhoneForMpesa($phone);
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);

    $payload = [
        'BusinessShortCode' => MPESA_SHORTCODE,
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline',
        'Amount'            => (int) round($amount),
        'PartyA'            => $phone,
        'PartyB'            => MPESA_SHORTCODE,
        'PhoneNumber'       => $phone,
        'CallBackURL'       => MPESA_CALLBACK_URL,
        'AccountReference'  => $accountRef,
        'TransactionDesc'   => $description,
    ];

    $ch = curl_init(mpesaBaseUrl() . '/mpesa/stkpush/v1/processrequest');
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
        return ['success' => false, 'error' => 'M-Pesa request failed: ' . $err];
    }

    $data = json_decode($response, true);

    if (isset($data['ResponseCode']) && $data['ResponseCode'] === '0') {
        return [
            'success' => true,
            'checkout_request_id' => $data['CheckoutRequestID'],
            'merchant_request_id' => $data['MerchantRequestID'],
        ];
    }

    return ['success' => false, 'error' => $data['errorMessage'] ?? 'STK push failed'];
}

// ---------------------------------------------------------
// Handle the Daraja callback (called from api/mpesa_callback.php)
// Returns parsed result: success, amount, mpesa_receipt, phone
// ---------------------------------------------------------
function mpesaParseCallback(array $callbackData): array {
    $stkCallback = $callbackData['Body']['stkCallback'] ?? null;
    if (!$stkCallback) {
        return ['success' => false, 'error' => 'Malformed callback'];
    }

    $resultCode = $stkCallback['ResultCode'];
    $checkoutRequestId = $stkCallback['CheckoutRequestID'];

    if ($resultCode !== 0) {
        return [
            'success' => false,
            'checkout_request_id' => $checkoutRequestId,
            'error' => $stkCallback['ResultDesc'] ?? 'Payment failed or cancelled',
        ];
    }

    $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
    $parsed = [];
    foreach ($items as $item) {
        $parsed[$item['Name']] = $item['Value'] ?? null;
    }

    return [
        'success' => true,
        'checkout_request_id' => $checkoutRequestId,
        'amount' => $parsed['Amount'] ?? null,
        'mpesa_receipt' => $parsed['MpesaReceiptNumber'] ?? null,
        'phone' => $parsed['PhoneNumber'] ?? null,
        'transaction_date' => $parsed['TransactionDate'] ?? null,
    ];
}

// M-Pesa wants 2547XXXXXXXX with no + or leading 0
function normalizePhoneForMpesa(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
        $phone = '254' . $phone;
    }
    return $phone;
}
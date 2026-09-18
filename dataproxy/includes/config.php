<?php
// =========================================================
// config.php — fill these in before deploying
// Keep this file OUT of version control (.gitignore it)
// =========================================================

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'dataproxy_platform');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- M-Pesa Daraja API ---
define('MPESA_ENV', 'sandbox');            // 'sandbox' or 'production'
define('MPESA_CONSUMER_KEY', 'VDPGQYlDRDCDLGm3ims2gFAIqhguOLbPG8x49Y8iGGFuFy3qyour_consumer_key');
define('MPESA_CONSUMER_SECRET', 'zB1GokKsV26UA7bNGzsQRJoiRnoTWO8osYxKgGcj82n7QWY8CPYS005Z49BSt4le');
define('MPESA_SHORTCODE', '174379');        // Paybill/Till number
define('MPESA_PASSKEY', 'your_lipa_na_mpesa_passkey');
define('MPESA_CALLBACK_URL', 'https://yourdomain.com/api/mpesa_callback.php');

// --- M-Pesa Daraja B2C (payouts to proxy participants) ---
// Separate Daraja product from STK Push above — apply for it
// separately in the Safaricom developer portal.
define('MPESA_B2C_ENABLED', false);         // flip to true once approved & configured
define('MPESA_B2C_INITIATOR_NAME', 'your_initiator_name');
define('MPESA_B2C_SECURITY_CREDENTIAL', 'your_encrypted_security_credential');
define('MPESA_B2C_SHORTCODE', 'your_b2c_shortcode');
define('MPESA_B2C_RESULT_URL', 'https://yourdomain.com/api/mpesa_b2c_callback.php');
define('MPESA_B2C_TIMEOUT_URL', 'https://yourdomain.com/api/mpesa_b2c_callback.php');

// --- Bundle delivery ---
// 'manual' (default, no external API needed) or 'api' (aggregator/dealer API)
define('BUNDLE_DELIVERY_MODE', 'manual');
define('BUNDLE_API_URL', '');               // e.g. https://aggregator.example.com/v1/deliver
define('BUNDLE_API_KEY', '');
define('ADMIN_OPS_PHONE', '');              // phone to SMS when a bundle needs manual delivery, e.g. 2547XXXXXXXX

// --- Africa's Talking SMS ---
define('AT_USERNAME', 'your_at_username');
define('AT_API_KEY', 'your_at_api_key');
define('AT_SENDER_ID', '');                 // optional shortcode/sender ID

// --- App ---
define('APP_NAME', 'DataProxy');
define('APP_URL', 'https://yourdomain.com');
define('SESSION_LIFETIME', 60 * 60 * 24 * 7); // 7 days

// --- Error reporting (turn OFF in production) ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // set to 0 in production

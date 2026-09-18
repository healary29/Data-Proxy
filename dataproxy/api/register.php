<?php
// =========================================================
// api/register.php
// POST: phone, password, email (optional)
// =========================================================
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';
$email = trim($input['email'] ?? '') ?: null;

if (!$phone || strlen($password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Valid phone number and password (min 6 chars) are required']);
    exit;
}

$result = registerUser($phone, $password, $email);
echo json_encode($result);

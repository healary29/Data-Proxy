<?php
// =========================================================
// api/login.php
// POST: phone, password
// =========================================================
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$phone = trim($input['phone'] ?? '');
$password = $input['password'] ?? '';

if (!$phone || !$password) {
    echo json_encode(['success' => false, 'error' => 'Phone number and password are required']);
    exit;
}

$result = loginUser($phone, $password);
echo json_encode($result);

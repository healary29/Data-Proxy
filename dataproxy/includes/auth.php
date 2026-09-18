<?php
// =========================================================
// auth.php — session, registration, login, guest helpers
// =========================================================
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------
// Registration (creates a real account — required for proxy)
// ---------------------------------------------------------
function registerUser(string $phone, string $password, ?string $email = null): array {
    $pdo = getDB();
    $phone = normalizePhone($phone);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Phone number already registered'];
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (phone, email, password_hash, is_guest) VALUES (?, ?, ?, 0)"
    );
    $stmt->execute([$phone, $email, $hash]);

    return ['success' => true, 'user_id' => $pdo->lastInsertId()];
}

// ---------------------------------------------------------
// Login
// ---------------------------------------------------------
function loginUser(string $phone, string $password): array {
    $pdo = getDB();
    $phone = normalizePhone($phone);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? AND is_guest = 0");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid phone number or password'];
    }

    if ($user['status'] === 'suspended') {
        return ['success' => false, 'error' => 'This account has been suspended'];
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['phone']   = $user['phone'];

    return ['success' => true, 'user' => $user];
}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
}

// ---------------------------------------------------------
// Current user / guards
// ---------------------------------------------------------
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function requireLogin(): array {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        header('Location: /public/login.php');
        exit;
    }
    return $user;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

// JSON-API variant of requireLogin — for api/*.php endpoints, which
// should return a 401 JSON error instead of redirecting.
function requireLoginJson(): array {
    $user = currentUser();
    if (!$user) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Please log in first']);
        exit;
    }
    return $user;
}

// ---------------------------------------------------------
// Guest order ref (for guest data-bundle checkout + ticket lookup)
// Format: DH-XXXXX (uppercase alphanumeric)
// ---------------------------------------------------------
function generateGuestOrderRef(): string {
    $pdo = getDB();
    do {
        $ref = 'DH-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        $stmt = $pdo->prepare("SELECT id FROM data_orders WHERE guest_order_ref = ?");
        $stmt->execute([$ref]);
    } while ($stmt->fetch());
    return $ref;
}

// Verify a guest phone + order ref match a real order (used before
// letting a guest view/raise a ticket)
function verifyGuestOrder(string $phone, string $orderRef): ?array {
    $pdo = getDB();
    $phone = normalizePhone($phone);
    $stmt = $pdo->prepare(
        "SELECT * FROM data_orders WHERE guest_phone = ? AND guest_order_ref = ?"
    );
    $stmt->execute([$phone, $orderRef]);
    $order = $stmt->fetch();
    return $order ?: null;
}

// ---------------------------------------------------------
// Phone normalization — store all numbers as 2547XXXXXXXX
// ---------------------------------------------------------
function normalizePhone(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone); // strip non-digits
    if (str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
        $phone = '254' . $phone;
    } elseif (str_starts_with($phone, '+254')) {
        $phone = substr($phone, 1);
    }
    return $phone;
}

<?php
// =========================================================
// admin_auth.php — admin session, login, guards
// Uses a distinct session key ($_SESSION['admin_id']) so admin
// and customer logins never collide even from the same browser.
// =========================================================
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function adminLogin(string $username, string $password): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        return ['success' => false, 'error' => 'Invalid username or password'];
    }

    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_role'] = $admin['role'];

    return ['success' => true];
}

function adminLogout(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_role']);
}

function currentAdmin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, username, role FROM admin_users WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    return $admin ?: null;
}

function requireAdmin(): array {
    $admin = currentAdmin();
    if (!$admin) {
        header('Location: /dataproxy/admin/login.php');
        exit;
    }
    return $admin;
}

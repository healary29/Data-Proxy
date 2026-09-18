<?php
// =========================================================
// _header.php — include at top of every public page
// Expects $pageTitle to be set before include
// =========================================================
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = currentUser();
$pageTitle = $pageTitle ?? 'DataProxy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — DataProxy</title>
  <link rel="stylesheet" href="/dataproxy/assets/css/style.css">
</head>
<body class="<?= $hasBottomNav ?? true ? 'has-bottom-nav' : '' ?>">
  <div class="topbar">
    <a href="/dataproxy/public/index.php" class="brand">DataProxy</a>
    <?php if ($user): ?>
      <a href="/dataproxy/public/dashboard.php" class="balance">
        <?= 'KSh ' . number_format($user['wallet_balance'], 0) ?>
      </a>
    <?php else: ?>
      <a href="/dataproxy/public/login.php" class="balance">Log in</a>
    <?php endif; ?>
  </div>
  <div class="container">

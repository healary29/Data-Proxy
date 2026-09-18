<?php
// =========================================================
// admin/_header.php — include at top of every admin page
// Expects $pageTitle and $activeNav to be set before include
// =========================================================
require_once __DIR__ . '/../includes/admin_auth.php';
$admin = requireAdmin();
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';

// Count open tickets for sidebar badge
$pdo = getDB();
$openTicketCount = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — Admin</title>
  <link rel="stylesheet" href="/dataproxy/assets/css/style.css">
  <link rel="stylesheet" href="/dataproxy/assets/css/admin.css">
</head>
<body class="admin-body">
  <div class="admin-topbar">
    <span class="admin-brand">DataProxy Admin</span>
    <span class="admin-user"><?= htmlspecialchars($admin['username']) ?> (<?= htmlspecialchars($admin['role']) ?>)
      · <a href="/dataproxy/admin/logout.php">Log out</a>
    </span>
  </div>

  <div class="admin-layout">
    <nav class="admin-sidebar">
      <a href="/dataproxy/admin/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a>
      <a href="/dataproxy/admin/bundles.php" class="<?= $activeNav === 'bundles' ? 'active' : '' ?>">📶 Data Bundles</a>
      <a href="/dataproxy/admin/plans.php" class="<?= $activeNav === 'plans' ? 'active' : '' ?>">🌐 Proxy Plans</a>
      <a href="/dataproxy/admin/orders.php" class="<?= $activeNav === 'orders' ? 'active' : '' ?>">🧾 Orders</a>
      <a href="/dataproxy/admin/devices.php" class="<?= $activeNav === 'devices' ? 'active' : '' ?>">📱 Devices</a>
      <a href="/dataproxy/admin/payouts.php" class="<?= $activeNav === 'payouts' ? 'active' : '' ?>">💸 Payouts</a>
      <a href="/dataproxy/admin/tickets.php" class="<?= $activeNav === 'tickets' ? 'active' : '' ?>">
        💬 Complaints <?php if ($openTicketCount): ?><span class="sidebar-badge"><?= $openTicketCount ?></span><?php endif; ?>
      </a>
    </nav>
    <main class="admin-main">

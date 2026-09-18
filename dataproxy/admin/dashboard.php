<?php
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/_header.php';

$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_guest = 0")->fetchColumn();
$walletTotal = (float) $pdo->query("SELECT COALESCE(SUM(wallet_balance),0) FROM users")->fetchColumn();
$dataOrdersToday = (int) $pdo->query("SELECT COUNT(*) FROM data_orders WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$proxyOrdersActive = (int) $pdo->query("SELECT COUNT(*) FROM proxy_orders WHERE status = 'active'")->fetchColumn();
$revenueToday = (float) $pdo->query("
    SELECT COALESCE(SUM(db.sell_price),0) FROM data_orders do
    JOIN data_bundles db ON db.id = do.bundle_id
    WHERE DATE(do.created_at) = CURDATE() AND do.status IN ('paid','delivered')
")->fetchColumn();
$devicesOnline = (int) $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'online'")->fetchColumn();
$pendingPayouts = (int) $pdo->query("SELECT COUNT(*) FROM payouts WHERE status = 'pending'")->fetchColumn();
$openTickets = (int) $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')")->fetchColumn();
?>

<div class="admin-toolbar"><h2>Dashboard</h2></div>

<div class="stat-grid">
  <div class="stat-card"><div class="value"><?= $totalUsers ?></div><div class="label">Registered Users</div></div>
  <div class="stat-card"><div class="value">KSh <?= number_format($walletTotal) ?></div><div class="label">Total Wallet Balance</div></div>
  <div class="stat-card"><div class="value"><?= $dataOrdersToday ?></div><div class="label">Data Orders Today</div></div>
  <div class="stat-card"><div class="value">KSh <?= number_format($revenueToday) ?></div><div class="label">Revenue Today (Data)</div></div>
  <div class="stat-card"><div class="value"><?= $proxyOrdersActive ?></div><div class="label">Active Proxy Orders</div></div>
  <div class="stat-card"><div class="value"><?= $devicesOnline ?></div><div class="label">Devices Online</div></div>
  <div class="stat-card"><div class="value"><?= $pendingPayouts ?></div><div class="label">Pending Payouts</div></div>
  <div class="stat-card"><div class="value"><?= $openTickets ?></div><div class="label">Open Tickets</div></div>
</div>

<h3>Recent Data Orders</h3>
<table class="admin-table">
  <tr><th>Ref</th><th>Bundle</th><th>Recipient</th><th>Status</th><th>Date</th></tr>
  <?php
  $recent = $pdo->query("
      SELECT do.*, db.name AS bundle_name FROM data_orders do
      JOIN data_bundles db ON db.id = do.bundle_id
      ORDER BY do.created_at DESC LIMIT 8
  ")->fetchAll();
  foreach ($recent as $r): ?>
    <tr>
      <td><?= htmlspecialchars($r['guest_order_ref']) ?></td>
      <td><?= htmlspecialchars($r['bundle_name']) ?></td>
      <td><?= htmlspecialchars($r['recipient_phone']) ?></td>
      <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
      <td><?= date('d M, H:i', strtotime($r['created_at'])) ?></td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

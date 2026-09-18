<?php
$pageTitle = 'Orders';
$activeNav = 'orders';
require __DIR__ . '/_header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_data_status') {
        $pdo->prepare("UPDATE data_orders SET status = ? WHERE id = ?")
            ->execute([$_POST['status'], (int) $_POST['id']]);
        $msg = 'Order status updated.';
    } elseif ($action === 'update_proxy_status') {
        $pdo->prepare("UPDATE proxy_orders SET status = ? WHERE id = ?")
            ->execute([$_POST['status'], (int) $_POST['id']]);
        $msg = 'Proxy status updated.';
    }
}

$tab = $_GET['tab'] ?? 'data';
?>

<div class="admin-toolbar"><h2>Orders</h2></div>
<?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="tabs">
  <a href="?tab=data" class="<?= $tab === 'data' ? 'active' : '' ?>">Data Orders</a>
  <a href="?tab=proxy" class="<?= $tab === 'proxy' ? 'active' : '' ?>">Proxy Orders</a>
</div>

<?php if ($tab === 'data'):
  $orders = $pdo->query("
      SELECT do.*, db.name AS bundle_name,
        COALESCE(u.phone, do.guest_phone) AS payer_phone
      FROM data_orders do
      JOIN data_bundles db ON db.id = do.bundle_id
      LEFT JOIN users u ON u.id = do.user_id
      ORDER BY do.created_at DESC LIMIT 100
  ")->fetchAll();
?>
<table class="admin-table">
  <tr><th>Ref</th><th>Bundle</th><th>Payer</th><th>Recipient</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= htmlspecialchars($o['guest_order_ref'] ?? '—') ?></td>
      <td><?= htmlspecialchars($o['bundle_name']) ?></td>
      <td><?= htmlspecialchars($o['payer_phone']) ?></td>
      <td><?= htmlspecialchars($o['recipient_phone']) ?></td>
      <td><?= $o['payment_method'] === 'wallet' ? 'Wallet' : 'M-Pesa Direct' ?></td>
      <td><span class="badge <?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
      <td><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
      <td>
        <form method="POST" style="display:flex; gap:4px;">
          <input type="hidden" name="action" value="update_data_status">
          <input type="hidden" name="id" value="<?= $o['id'] ?>">
          <select name="status" style="margin:0; padding:4px; font-size:0.8rem;">
            <?php foreach (['pending','paid','delivered','failed','refunded'] as $s): ?>
              <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn secondary btn-sm">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php else:
  $orders = $pdo->query("
      SELECT po.*, pp.name AS plan_name, u.phone AS user_phone
      FROM proxy_orders po
      JOIN proxy_plans pp ON pp.id = po.plan_id
      JOIN users u ON u.id = po.user_id
      ORDER BY po.created_at DESC LIMIT 100
  ")->fetchAll();
?>
<table class="admin-table">
  <tr><th>User</th><th>Plan</th><th>Host</th><th>Expires</th><th>Status</th><th>Date</th><th>Actions</th></tr>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= htmlspecialchars($o['user_phone']) ?></td>
      <td><?= htmlspecialchars($o['plan_name']) ?></td>
      <td><?= htmlspecialchars($o['proxy_host'] ?? '—') ?></td>
      <td><?= date('d M Y', strtotime($o['expires_at'])) ?></td>
      <td><span class="badge <?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
      <td><?= date('d M, H:i', strtotime($o['created_at'])) ?></td>
      <td>
        <form method="POST" style="display:flex; gap:4px;">
          <input type="hidden" name="action" value="update_proxy_status">
          <input type="hidden" name="id" value="<?= $o['id'] ?>">
          <select name="status" style="margin:0; padding:4px; font-size:0.8rem;">
            <?php foreach (['active','expired','revoked'] as $s): ?>
              <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn secondary btn-sm">Save</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

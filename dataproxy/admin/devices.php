<?php
$pageTitle = 'Devices';
$activeNav = 'devices';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/device_cleanup.php';
markStaleDevicesOffline();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'ban') {
        $pdo->prepare("UPDATE devices SET status = 'banned' WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = 'Device banned.';
    } elseif ($action === 'unban') {
        $pdo->prepare("UPDATE devices SET status = 'offline' WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = 'Device reinstated.';
    }
}

$devices = $pdo->query("
    SELECT d.*, u.phone AS owner_phone
    FROM devices d
    JOIN users u ON u.id = d.participant_user_id
    ORDER BY d.status = 'online' DESC, d.last_seen DESC
")->fetchAll();

$onlineCount = (int) $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'online'")->fetchColumn();
?>

<div class="admin-toolbar"><h2>Participant Devices</h2></div>
<?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="stat-grid" style="margin-bottom:20px;">
  <div class="stat-card"><div class="value"><?= count($devices) ?></div><div class="label">Total Devices</div></div>
  <div class="stat-card"><div class="value"><?= $onlineCount ?></div><div class="label">Online Now</div></div>
</div>

<table class="admin-table">
  <tr><th>Owner</th><th>Label</th><th>Exit IP</th><th>Status</th><th>Last Seen</th><th>Actions</th></tr>
  <?php foreach ($devices as $d): ?>
    <tr>
      <td><?= htmlspecialchars($d['owner_phone']) ?></td>
      <td><?= htmlspecialchars($d['device_label'] ?? '—') ?></td>
      <td><?= htmlspecialchars($d['exit_ip'] ?? '—') ?></td>
      <td><span class="badge <?= $d['status'] === 'online' ? 'active' : ($d['status'] === 'banned' ? 'revoked' : 'closed') ?>"><?= ucfirst($d['status']) ?></span></td>
      <td><?= $d['last_seen'] ? date('d M, H:i', strtotime($d['last_seen'])) : 'Never' ?></td>
      <td>
        <?php if ($d['status'] === 'banned'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="unban">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn secondary btn-sm">Reinstate</button>
          </form>
        <?php else: ?>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Ban this device?');">
            <input type="hidden" name="action" value="ban">
            <input type="hidden" name="id" value="<?= $d['id'] ?>">
            <button type="submit" class="btn danger btn-sm">Ban</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

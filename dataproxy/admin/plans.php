<?php
$pageTitle = 'Proxy Plans';
$activeNav = 'plans';
require __DIR__ . '/_header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $pdo->prepare(
            "INSERT INTO proxy_plans (name, bandwidth_gb, duration_days, price) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            trim($_POST['name']),
            $_POST['bandwidth_gb'] !== '' ? (int) $_POST['bandwidth_gb'] : null,
            (int) $_POST['duration_days'], (float) $_POST['price'],
        ]);
        $msg = 'Plan added.';
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE proxy_plans SET is_active = NOT is_active WHERE id = ?")
            ->execute([(int) $_POST['id']]);
        $msg = 'Plan updated.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM proxy_plans WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = 'Plan deleted.';
    }
}

$plans = $pdo->query("SELECT * FROM proxy_plans ORDER BY price ASC")->fetchAll();
?>

<div class="admin-toolbar"><h2>Proxy Plans</h2></div>
<?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <h3 class="mt-0">Add New Plan</h3>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div><label>Name</label><input type="text" name="name" placeholder="e.g. 7-Day Unlimited" required></div>
      <div><label>Bandwidth (GB, optional)</label><input type="number" name="bandwidth_gb"></div>
    </div>
    <div class="form-row">
      <div><label>Duration (days)</label><input type="number" name="duration_days" required></div>
      <div><label>Price (KSh)</label><input type="number" step="0.01" name="price" required></div>
    </div>
    <button type="submit" class="btn">Add Plan</button>
  </form>
</div>

<table class="admin-table">
  <tr><th>Name</th><th>Bandwidth</th><th>Duration</th><th>Price</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($plans as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['name']) ?></td>
      <td><?= $p['bandwidth_gb'] ? $p['bandwidth_gb'] . 'GB' : 'Unlimited' ?></td>
      <td><?= $p['duration_days'] ?>d</td>
      <td>KSh <?= number_format($p['price'], 2) ?></td>
      <td><span class="badge <?= $p['is_active'] ? 'active' : 'closed' ?>"><?= $p['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn secondary btn-sm"><?= $p['is_active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this plan?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $p['id'] ?>">
          <button type="submit" class="btn danger btn-sm">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

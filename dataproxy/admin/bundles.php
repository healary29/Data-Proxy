<?php
$pageTitle = 'Data Bundles';
$activeNav = 'bundles';
require __DIR__ . '/_header.php';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $pdo->prepare(
            "INSERT INTO data_bundles (name, size_mb, validity_days, cost_price, sell_price) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            trim($_POST['name']), (int) $_POST['size_mb'], (int) $_POST['validity_days'],
            (float) $_POST['cost_price'], (float) $_POST['sell_price'],
        ]);
        $msg = 'Bundle added.';
    } elseif ($action === 'toggle') {
        $pdo->prepare("UPDATE data_bundles SET is_active = NOT is_active WHERE id = ?")
            ->execute([(int) $_POST['id']]);
        $msg = 'Bundle updated.';
    } elseif ($action === 'delete') {
        $pdo->prepare("DELETE FROM data_bundles WHERE id = ?")->execute([(int) $_POST['id']]);
        $msg = 'Bundle deleted.';
    }
}

$bundles = $pdo->query("SELECT * FROM data_bundles ORDER BY sell_price ASC")->fetchAll();
?>

<div class="admin-toolbar"><h2>Data Bundles</h2></div>
<?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <h3 class="mt-0">Add New Bundle</h3>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="form-row">
      <div><label>Name</label><input type="text" name="name" placeholder="e.g. 1GB Daily" required></div>
      <div><label>Size (MB)</label><input type="number" name="size_mb" required></div>
    </div>
    <div class="form-row">
      <div><label>Validity (days)</label><input type="number" name="validity_days" required></div>
      <div><label>Cost Price (KSh)</label><input type="number" step="0.01" name="cost_price" required></div>
      <div><label>Sell Price (KSh)</label><input type="number" step="0.01" name="sell_price" required></div>
    </div>
    <button type="submit" class="btn">Add Bundle</button>
  </form>
</div>

<table class="admin-table">
  <tr><th>Name</th><th>Size</th><th>Validity</th><th>Cost</th><th>Sell</th><th>Status</th><th>Actions</th></tr>
  <?php foreach ($bundles as $b): ?>
    <tr>
      <td><?= htmlspecialchars($b['name']) ?></td>
      <td><?= $b['size_mb'] >= 1024 ? round($b['size_mb']/1024,1).'GB' : $b['size_mb'].'MB' ?></td>
      <td><?= $b['validity_days'] ?>d</td>
      <td>KSh <?= number_format($b['cost_price'], 2) ?></td>
      <td>KSh <?= number_format($b['sell_price'], 2) ?></td>
      <td><span class="badge <?= $b['is_active'] ? 'active' : 'closed' ?>"><?= $b['is_active'] ? 'Active' : 'Inactive' ?></span></td>
      <td>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn secondary btn-sm"><?= $b['is_active'] ? 'Disable' : 'Enable' ?></button>
        </form>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this bundle?');">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $b['id'] ?>">
          <button type="submit" class="btn danger btn-sm">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

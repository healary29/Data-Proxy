<?php
$pageTitle = 'Complaints';
$activeNav = 'tickets';
require __DIR__ . '/_header.php';

$statusFilter = $_GET['status'] ?? 'open';
$where = '';
$params = [];
if ($statusFilter !== 'all') {
    $where = "WHERE t.status = ?";
    $params[] = $statusFilter;
}

$stmt = $pdo->prepare("
    SELECT t.*, COALESCE(u.phone, t.guest_phone) AS contact_phone,
      TIMESTAMPDIFF(HOUR, t.created_at, NOW()) AS hours_open
    FROM tickets t
    LEFT JOIN users u ON u.id = t.user_id
    $where
    ORDER BY t.status = 'open' DESC, t.created_at ASC
");
$stmt->execute($params);
$tickets = $stmt->fetchAll();
?>

<div class="admin-toolbar"><h2>Complaints</h2></div>

<div class="tabs">
  <a href="?status=open" class="<?= $statusFilter === 'open' ? 'active' : '' ?>">Open</a>
  <a href="?status=in_progress" class="<?= $statusFilter === 'in_progress' ? 'active' : '' ?>">In Progress</a>
  <a href="?status=resolved" class="<?= $statusFilter === 'resolved' ? 'active' : '' ?>">Resolved</a>
  <a href="?status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">All</a>
</div>

<table class="admin-table">
  <tr><th>Subject</th><th>Contact</th><th>Category</th><th>Status</th><th>Opened</th><th></th></tr>
  <?php foreach ($tickets as $t): ?>
    <tr>
      <td><?= htmlspecialchars($t['subject']) ?></td>
      <td><?= htmlspecialchars($t['contact_phone'] ?? '—') ?></td>
      <td><?= ucfirst($t['order_type']) ?></td>
      <td>
        <span class="badge <?= $t['status'] ?>"><?= ucfirst(str_replace('_',' ',$t['status'])) ?></span>
        <?php if ($t['status'] === 'open' && $t['hours_open'] > 24): ?>
          <span class="badge failed" title="Overdue">⚠ Overdue</span>
        <?php endif; ?>
      </td>
      <td><?= date('d M, H:i', strtotime($t['created_at'])) ?></td>
      <td><a href="/dataproxy/admin/ticket_detail.php?id=<?= $t['id'] ?>" class="btn secondary btn-sm">Open</a></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($tickets)): ?>
    <tr><td colspan="6" class="text-muted">No tickets in this view.</td></tr>
  <?php endif; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

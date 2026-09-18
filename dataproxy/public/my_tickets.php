<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();

$pageTitle = 'Support';
$activeNav = 'tickets';
require __DIR__ . '/_header.php';
?>

<h2>Support Tickets</h2>

<?php if (!$user): ?>
  <div class="alert info">Log in to view your ticket history, or track a guest order below.</div>
  <a href="/dataproxy/public/login.php" class="btn">Log In</a>
  <br><br>
  <a href="/dataproxy/public/guest_lookup.php" class="btn secondary">Track a Guest Order</a>

<?php else:
  $pdo = getDB();
  $stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC");
  $stmt->execute([$user['id']]);
  $tickets = $stmt->fetchAll();
?>

  <a href="/dataproxy/public/raise_ticket_form.php" class="btn" style="margin-bottom:16px;">Raise New Ticket</a>

  <div class="card">
    <?php if (empty($tickets)): ?>
      <p class="mt-0 text-muted">No tickets yet.</p>
    <?php else: foreach ($tickets as $t): ?>
      <div class="list-item">
        <div>
          <strong><?= htmlspecialchars($t['subject']) ?></strong><br>
          <span class="meta"><?= ucfirst($t['order_type']) ?> · <?= date('d M, H:i', strtotime($t['created_at'])) ?></span>
        </div>
        <a href="/dataproxy/public/ticket_view.php?id=<?= $t['id'] ?>" class="badge <?= $t['status'] ?>"><?= ucfirst(str_replace('_',' ', $t['status'])) ?></a>
      </div>
    <?php endforeach; endif; ?>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

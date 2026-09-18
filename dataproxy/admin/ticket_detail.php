<?php
require_once __DIR__ . '/../includes/sms.php';
$pageTitle = 'Ticket Detail';
$activeNav = 'tickets';
require __DIR__ . '/_header.php';

$ticketId = (int) ($_GET['id'] ?? 0);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $message = trim($_POST['message'] ?? '');
        if ($message) {
            $pdo->prepare("INSERT INTO ticket_replies (ticket_id, sender, message) VALUES (?, 'admin', ?)")
                ->execute([$ticketId, $message]);
            $pdo->prepare("UPDATE tickets SET status = 'in_progress' WHERE id = ? AND status = 'open'")
                ->execute([$ticketId]);
            $msg = 'Reply sent.';
        }
    } elseif ($action === 'set_status') {
        $pdo->prepare("UPDATE tickets SET status = ? WHERE id = ?")
            ->execute([$_POST['status'], $ticketId]);
        $msg = 'Status updated.';
    } elseif ($action === 'set_priority') {
        $pdo->prepare("UPDATE tickets SET priority = ? WHERE id = ?")
            ->execute([$_POST['priority'], $ticketId]);
        $msg = 'Priority updated.';
    } elseif ($action === 'refund') {
        $amount = (float) $_POST['refund_amount'];
        $userId = (int) $_POST['user_id'];
        if ($amount > 0 && $userId) {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                ->execute([$amount, $userId]);
            $pdo->prepare(
                "INSERT INTO wallet_transactions (user_id, type, amount, status, notes) VALUES (?, 'refund', ?, 'completed', ?)"
            )->execute([$userId, $amount, "Refund for ticket #$ticketId"]);
            $pdo->commit();
            $msg = "KSh $amount refunded to wallet.";
        }
    }
}

$stmt = $pdo->prepare("SELECT t.*, COALESCE(u.phone, t.guest_phone) AS contact_phone, u.id AS reg_user_id
    FROM tickets t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ?");
$stmt->execute([$ticketId]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo '<div class="alert error">Ticket not found.</div>';
    require __DIR__ . '/_footer.php';
    exit;
}

// Pull order context automatically
$orderContext = null;
if ($ticket['order_type'] === 'data' && $ticket['order_id']) {
    $s = $pdo->prepare("SELECT do.*, db.name AS bundle_name, db.sell_price FROM data_orders do
        JOIN data_bundles db ON db.id = do.bundle_id WHERE do.id = ?");
    $s->execute([$ticket['order_id']]);
    $orderContext = $s->fetch();
} elseif ($ticket['order_type'] === 'proxy' && $ticket['order_id']) {
    $s = $pdo->prepare("SELECT po.*, pp.name AS plan_name, pp.price FROM proxy_orders po
        JOIN proxy_plans pp ON pp.id = po.plan_id WHERE po.id = ?");
    $s->execute([$ticket['order_id']]);
    $orderContext = $s->fetch();
}

$stmt = $pdo->prepare("SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
$stmt->execute([$ticketId]);
$replies = $stmt->fetchAll();
?>

<a href="/dataproxy/admin/tickets.php" class="text-muted">&larr; Back to Complaints</a>
<div class="admin-toolbar" style="margin-top:8px;">
  <h2>#<?= $ticket['id'] ?> — <?= htmlspecialchars($ticket['subject']) ?></h2>
  <span class="badge <?= $ticket['status'] ?>"><?= ucfirst(str_replace('_',' ',$ticket['status'])) ?></span>
</div>
<?php if ($msg): ?><div class="alert success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<div class="card">
  <strong>Contact:</strong> <?= htmlspecialchars($ticket['contact_phone'] ?? '—') ?>
  &nbsp;·&nbsp; <strong>Category:</strong> <?= ucfirst($ticket['order_type']) ?>
  &nbsp;·&nbsp; <strong>Priority:</strong> <?= ucfirst($ticket['priority']) ?>
  &nbsp;·&nbsp; <strong>Opened:</strong> <?= date('d M Y, H:i', strtotime($ticket['created_at'])) ?>
</div>

<?php if ($orderContext): ?>
<div class="card">
  <h3 class="mt-0">Order Context</h3>
  <?php if ($ticket['order_type'] === 'data'): ?>
    <p><strong><?= htmlspecialchars($orderContext['bundle_name']) ?></strong> — KSh <?= number_format($orderContext['sell_price'],2) ?><br>
    Ref: <?= htmlspecialchars($orderContext['guest_order_ref'] ?? '—') ?> ·
    M-Pesa: <?= htmlspecialchars($orderContext['mpesa_ref'] ?? '—') ?> ·
    Status: <span class="badge <?= $orderContext['status'] ?>"><?= ucfirst($orderContext['status']) ?></span></p>
  <?php else: ?>
    <p><strong><?= htmlspecialchars($orderContext['plan_name']) ?></strong> — KSh <?= number_format($orderContext['price'],2) ?><br>
    Expires: <?= date('d M Y', strtotime($orderContext['expires_at'])) ?> ·
    Status: <span class="badge <?= $orderContext['status'] ?>"><?= ucfirst($orderContext['status']) ?></span></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="mt-0">Conversation</h3>
  <?php foreach ($replies as $r): ?>
    <div style="margin-bottom:12px; <?= $r['sender'] === 'admin' ? 'padding-left:16px; border-left:3px solid var(--primary);' : '' ?>">
      <strong><?= $r['sender'] === 'admin' ? 'Support Team' : 'Customer' ?></strong>
      <span class="meta"><?= date('d M, H:i', strtotime($r['created_at'])) ?></span>
      <p style="margin:4px 0 0;"><?= nl2br(htmlspecialchars($r['message'])) ?></p>
    </div>
  <?php endforeach; ?>

  <form method="POST" style="margin-top:16px;">
    <input type="hidden" name="action" value="reply">
    <textarea name="message" placeholder="Type your reply..." required></textarea>
    <button type="submit" class="btn">Send Reply</button>
  </form>
</div>

<div class="card">
  <h3 class="mt-0">Quick Actions</h3>
  <div class="form-row">
    <form method="POST">
      <input type="hidden" name="action" value="set_status">
      <label>Status</label>
      <select name="status" onchange="this.form.submit()">
        <?php foreach (['open','in_progress','resolved','closed'] as $s): ?>
          <option value="<?= $s ?>" <?= $ticket['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <form method="POST">
      <input type="hidden" name="action" value="set_priority">
      <label>Priority</label>
      <select name="priority" onchange="this.form.submit()">
        <?php foreach (['low','normal','high'] as $p): ?>
          <option value="<?= $p ?>" <?= $ticket['priority'] === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if ($ticket['reg_user_id']): ?>
    <hr style="border:none; border-top:1px solid var(--border); margin:16px 0;">
    <h4 style="margin-bottom:8px;">Refund to Wallet</h4>
    <form method="POST" class="form-row" style="align-items:flex-end;">
      <input type="hidden" name="action" value="refund">
      <input type="hidden" name="user_id" value="<?= $ticket['reg_user_id'] ?>">
      <div><label>Amount (KSh)</label><input type="number" step="0.01" name="refund_amount" placeholder="0.00" required></div>
      <div><button type="submit" class="btn danger" onclick="return confirm('Refund this amount to the customer\'s wallet?');">Issue Refund</button></div>
    </form>
  <?php else: ?>
    <p class="text-muted" style="margin-top:12px;">This is a guest order — refunds must be processed manually via M-Pesa (no wallet to credit).</p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>

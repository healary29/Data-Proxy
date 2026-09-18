<?php
$pageTitle = 'Payouts';
$activeNav = 'payouts';
require __DIR__ . '/_header.php';
require_once __DIR__ . '/../includes/mpesa_b2c.php';

$msg = '';
$msgType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int) $_POST['id'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("SELECT p.*, u.phone FROM payouts p JOIN users u ON u.id = p.device_owner_id WHERE p.id = ?");
        $stmt->execute([$id]);
        $payout = $stmt->fetch();

        $b2cResult = triggerB2cPayout($payout['phone'], $payout['amount'], "Proxy payout #$id");

        if ($b2cResult['success']) {
            // B2C is async — mark pending with the conversation ID, final status
            // updates when api/mpesa_b2c_callback.php receives Daraja's result.
            $pdo->prepare("UPDATE payouts SET mpesa_ref = ? WHERE id = ?")
                ->execute([$b2cResult['conversation_id'], $id]);
            $msg = 'B2C payout initiated — will confirm once Daraja sends the result.';
        } else {
            $msg = 'Could not send automatically: ' . $b2cResult['error'] . '. Use "Mark Paid Manually" once you\'ve sent it yourself.';
            $msgType = 'error';
        }
    } elseif ($action === 'mark_manual') {
        $ref = 'MANUAL-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare("UPDATE payouts SET status = 'paid', mpesa_ref = ? WHERE id = ?")
            ->execute([$ref, $id]);
        $msg = 'Payout marked as manually paid.';
    } elseif ($action === 'reject') {
        $pdo->prepare("UPDATE payouts SET status = 'rejected' WHERE id = ?")->execute([$id]);
        $msg = 'Payout rejected.';
    }
}

$payouts = $pdo->query("
    SELECT p.*, u.phone AS owner_phone
    FROM payouts p
    JOIN users u ON u.id = p.device_owner_id
    ORDER BY p.status = 'pending' DESC, p.created_at DESC
")->fetchAll();
?>

<div class="admin-toolbar"><h2>Payouts</h2></div>
<?php if ($msg): ?><div class="alert <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

<p class="text-muted">B2C payouts require Daraja B2C approval (see config.php). Until then, "Send Payout" will fail gracefully — use "Mark Paid Manually" after sending the money yourself another way.</p>

<table class="admin-table">
  <tr><th>Owner</th><th>Amount</th><th>Status</th><th>Ref</th><th>Date</th><th>Actions</th></tr>
  <?php foreach ($payouts as $p): ?>
    <tr>
      <td><?= htmlspecialchars($p['owner_phone']) ?></td>
      <td>KSh <?= number_format($p['amount'], 2) ?></td>
      <td><span class="badge <?= $p['status'] === 'paid' ? 'active' : ($p['status'] === 'rejected' ? 'revoked' : 'pending') ?>"><?= ucfirst($p['status']) ?></span></td>
      <td><?= htmlspecialchars($p['mpesa_ref'] ?? '—') ?></td>
      <td><?= date('d M, H:i', strtotime($p['created_at'])) ?></td>
      <td>
        <?php if ($p['status'] === 'pending'): ?>
          <form method="POST" style="display:inline;">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn secondary btn-sm">Approve</button>
          </form>
          <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this payout?');">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button type="submit" class="btn danger btn-sm">Reject</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<?php require __DIR__ . '/_footer.php'; ?>

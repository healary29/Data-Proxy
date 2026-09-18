<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();

$pdo = getDB();
$bundles = $pdo->query("SELECT * FROM data_bundles WHERE is_active = 1 ORDER BY sell_price ASC")->fetchAll();

$pageTitle = 'Buy Data';
$activeNav = 'buy';
require __DIR__ . '/_header.php';
?>

<h2>Buy Data Bundle</h2>

<div id="alert" class="alert hidden"></div>
<div id="successBox" class="card hidden"></div>

<form id="buyForm">
  <label for="bundle_id">Choose Bundle</label>
  <select id="bundle_id" required>
    <option value="">Select a bundle...</option>
    <?php foreach ($bundles as $b): ?>
      <option value="<?= $b['id'] ?>">
        <?= htmlspecialchars($b['name']) ?> — <?= $b['size_mb'] >= 1024 ? round($b['size_mb']/1024,1).'GB' : $b['size_mb'].'MB' ?>
        (<?= $b['validity_days'] ?> days) — KSh <?= number_format($b['sell_price']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <label for="recipient_phone">Recipient Phone Number</label>
  <input type="tel" id="recipient_phone" placeholder="07XXXXXXXX" required>

  <?php if (!$user): ?>
    <label for="guest_phone">Your Phone Number (for M-Pesa payment)</label>
    <input type="tel" id="guest_phone" placeholder="07XXXXXXXX" required>
    <p class="text-muted">No account needed. You'll get an order reference to track this purchase.</p>
  <?php else: ?>
    <p class="text-muted">This will be paid from your wallet balance (<?= formatMoney($user['wallet_balance']) ?>).</p>
  <?php endif; ?>

  <button type="submit" class="btn" id="submitBtn">Buy Now</button>
</form>

<script>
document.getElementById('buyForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert('alert');
  document.getElementById('successBox').classList.add('hidden');

  const btn = document.getElementById('submitBtn');
  setLoading(btn, true, 'Processing...');

  const payload = {
    bundle_id: document.getElementById('bundle_id').value,
    recipient_phone: document.getElementById('recipient_phone').value,
  };
  const guestPhoneEl = document.getElementById('guest_phone');
  if (guestPhoneEl) payload.guest_phone = guestPhoneEl.value;

  const res = await apiPost('/dataproxy/api/buy_bundle.php', payload);

  setLoading(btn, false);

  if (res.success) {
    const box = document.getElementById('successBox');
    box.classList.remove('hidden');
    if (res.paid_from === 'wallet') {
      box.innerHTML = `<div class="alert success">Order placed! Ref: <strong>${res.order_ref}</strong></div>`;
    } else {
      box.innerHTML = `<div class="alert success">Enter your M-Pesa PIN on your phone to complete payment.
        <br><br>Save this order reference: <strong>${res.order_ref}</strong></div>`;
    }
    document.getElementById('buyForm').reset();
  } else {
    showAlert('alert', res.error || 'Purchase failed');
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
$user = requireLogin();

$pdo = getDB();

$stmt = $pdo->prepare(
    "SELECT do.*, db.name AS bundle_name FROM data_orders do
     JOIN data_bundles db ON db.id = do.bundle_id
     WHERE do.user_id = ? ORDER BY do.created_at DESC LIMIT 10"
);
$stmt->execute([$user['id']]);
$dataOrders = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT po.*, pp.name AS plan_name FROM proxy_orders po
     JOIN proxy_plans pp ON pp.id = po.plan_id
     WHERE po.user_id = ? ORDER BY po.created_at DESC LIMIT 10"
);
$stmt->execute([$user['id']]);
$proxyOrders = $stmt->fetchAll();

$pageTitle = 'Account';
$activeNav = 'account';
require __DIR__ . '/_header.php';
?>

<div class="card text-center">
  <p class="text-muted mt-0">Wallet Balance</p>
  <h2 style="margin: 4px 0;"><?= formatMoney($user['wallet_balance']) ?></h2>
  <button class="btn" id="topupBtn" onclick="document.getElementById('topupModal').classList.remove('hidden')">Top Up Wallet</button>
</div>

<div id="topupModal" class="card hidden">
  <h3 class="mt-0">Top Up via M-Pesa</h3>
  <div id="topupAlert" class="alert hidden"></div>
  <label for="topupAmount">Amount (KSh)</label>
  <input type="number" id="topupAmount" min="10" placeholder="e.g. 100">
  <button class="btn" id="topupSubmit">Send STK Push</button>
</div>

<h3>Recent Data Orders</h3>
<div class="card">
  <?php if (empty($dataOrders)): ?>
    <p class="text-muted mt-0">No orders yet.</p>
  <?php else: foreach ($dataOrders as $o): ?>
    <div class="list-item">
      <div>
        <strong><?= htmlspecialchars($o['bundle_name']) ?></strong><br>
        <span class="meta">Ref: <?= htmlspecialchars($o['guest_order_ref']) ?> · <?= date('d M, H:i', strtotime($o['created_at'])) ?></span>
      </div>
      <span class="badge <?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<h3>Active Proxies</h3>
<div class="card">
  <?php if (empty($proxyOrders)): ?>
    <p class="text-muted mt-0">No proxy plans yet.</p>
  <?php else: foreach ($proxyOrders as $p): ?>
    <div class="list-item">
      <div>
        <strong><?= htmlspecialchars($p['plan_name']) ?></strong><br>
        <span class="meta">Expires: <?= date('d M Y', strtotime($p['expires_at'])) ?></span>
      </div>
      <span class="badge <?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<a href="/dataproxy/api/logout.php" class="btn danger" style="margin-top:10px;">Log Out</a>
<br><br>
<a href="/dataproxy/public/my_devices.php" class="btn secondary">Become a Proxy Participant</a>

<script>
document.getElementById('topupSubmit').addEventListener('click', async () => {
  hideAlert('topupAlert');
  const amount = document.getElementById('topupAmount').value;
  if (!amount || amount < 10) {
    showAlert('topupAlert', 'Enter at least KSh 10');
    return;
  }
  const btn = document.getElementById('topupSubmit');
  setLoading(btn, true, 'Sending...');

  const res = await apiPost('/dataproxy/api/wallet_topup.php', { amount });

  setLoading(btn, false);

  if (res.success) {
    showAlert('topupAlert', res.message, 'success');
  } else {
    showAlert('topupAlert', res.error || 'Top-up failed');
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$user = requireLogin();

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM devices WHERE participant_user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$devices = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM payouts WHERE device_owner_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['id']]);
$payouts = $stmt->fetchAll();

$pageTitle = 'My Devices';
$hasBottomNav = true;
$activeNav = 'account';
require __DIR__ . '/_header.php';
?>

<h2>Become a Proxy Participant</h2>
<p class="text-muted">Register this device to share your connection as a proxy exit node and earn payouts. Keep this page open (or run our client app once available) to stay online.</p>

<div id="alert" class="alert hidden"></div>

<div class="card">
  <h3 class="mt-0">This Browser Session</h3>
  <p id="statusLine" class="text-muted">Not registered yet.</p>
  <button class="btn" id="registerBtn">Register This Device</button>
</div>

<h3>My Devices</h3>
<div class="card">
  <?php if (empty($devices)): ?>
    <p class="mt-0 text-muted">No devices registered yet.</p>
  <?php else: foreach ($devices as $d): ?>
    <div class="list-item">
      <div>
        <strong><?= htmlspecialchars($d['device_label'] ?? 'Device #' . $d['id']) ?></strong><br>
        <span class="meta">IP: <?= htmlspecialchars($d['exit_ip'] ?? '—') ?> · Last seen: <?= $d['last_seen'] ? date('d M, H:i', strtotime($d['last_seen'])) : 'Never' ?></span>
      </div>
      <span class="badge <?= $d['status'] === 'online' ? 'active' : ($d['status'] === 'banned' ? 'revoked' : 'closed') ?>"><?= ucfirst($d['status']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<h3>Payout History</h3>
<div class="card">
  <?php if (empty($payouts)): ?>
    <p class="mt-0 text-muted">No payouts yet.</p>
  <?php else: foreach ($payouts as $p): ?>
    <div class="list-item">
      <div><?= formatMoney($p['amount']) ?><br><span class="meta"><?= date('d M, H:i', strtotime($p['created_at'])) ?></span></div>
      <span class="badge <?= $p['status'] === 'paid' ? 'active' : ($p['status'] === 'rejected' ? 'revoked' : 'pending') ?>"><?= ucfirst($p['status']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
let deviceId = null;
let heartbeatInterval = null;

async function registerDevice() {
  const res = await apiPost('/dataproxy/api/device_heartbeat.php', {
    device_label: navigator.platform + ' — ' + navigator.userAgent.split(')')[0].split('(')[1],
    device_info: navigator.userAgent,
  });

  if (res.success) {
    deviceId = res.device_id;
    document.getElementById('statusLine').textContent = 'Registered as device #' + deviceId + '. Sending heartbeat every 60s while this page is open.';
    document.getElementById('registerBtn').textContent = 'Registered ✓';
    document.getElementById('registerBtn').disabled = true;
    startHeartbeat();
  } else {
    showAlert('alert', res.error || 'Could not register device');
  }
}

function startHeartbeat() {
  heartbeatInterval = setInterval(async () => {
    if (!deviceId) return;
    await apiPost('/dataproxy/api/device_heartbeat.php', { device_id: deviceId });
  }, 60000);
}

document.getElementById('registerBtn').addEventListener('click', registerDevice);
</script>

<?php require __DIR__ . '/_footer.php'; ?>

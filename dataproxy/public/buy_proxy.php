<?php
require_once __DIR__ . '/../includes/auth.php';
$user = currentUser();

$pdo = getDB();
$plans = $pdo->query("SELECT * FROM proxy_plans WHERE is_active = 1 ORDER BY price ASC")->fetchAll();

$pageTitle = 'Buy Proxy';
$activeNav = 'proxy';
require __DIR__ . '/_header.php';
?>

<h2>Buy Proxy Access</h2>

<?php if (!$user): ?>
  <div class="alert info">You need an account to buy proxy access — it lets us issue and track your credentials.</div>
  <a href="/dataproxy/public/register.php" class="btn">Create Account</a>
  <br><br>
  <a href="/dataproxy/public/login.php" class="btn secondary">Log In</a>

<?php else: ?>

  <div id="alert" class="alert hidden"></div>
  <div id="successBox" class="card hidden"></div>

  <p class="text-muted">Paid from your wallet balance (<?= formatMoney($user['wallet_balance']) ?>).</p>

  <?php if (empty($plans)): ?>
    <div class="card"><p class="mt-0 text-muted">No proxy plans available right now.</p></div>
  <?php else: foreach ($plans as $p): ?>
    <div class="card">
      <h3 class="mt-0"><?= htmlspecialchars($p['name']) ?></h3>
      <p class="text-muted">
        <?= $p['bandwidth_gb'] ? $p['bandwidth_gb'] . 'GB bandwidth · ' : '' ?><?= $p['duration_days'] ?> days
      </p>
      <p><strong><?= formatMoney($p['price']) ?></strong></p>
      <button class="btn buy-plan-btn" data-plan-id="<?= $p['id'] ?>">Buy This Plan</button>
    </div>
  <?php endforeach; endif; ?>

  <script>
  document.querySelectorAll('.buy-plan-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      hideAlert('alert');
      document.getElementById('successBox').classList.add('hidden');
      setLoading(btn, true, 'Processing...');

      const res = await apiPost('/dataproxy/api/buy_proxy.php', { plan_id: btn.dataset.planId });

      setLoading(btn, false);

      if (res.success) {
        const box = document.getElementById('successBox');
        box.classList.remove('hidden');
        box.innerHTML = `<div class="alert success">
          Proxy activated!<br>
          Host: <strong>${res.proxy.host}</strong>:<strong>${res.proxy.port}</strong><br>
          Username: <strong>${res.proxy.username}</strong><br>
          Password: <strong>${res.proxy.password}</strong><br>
          Expires: ${res.proxy.expires_at}
        </div>`;
      } else {
        showAlert('alert', res.error || 'Purchase failed');
      }
    });
  });
  </script>

<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>

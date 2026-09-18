<?php
$pageTitle = 'Home';
$activeNav = 'home';
require __DIR__ . '/_header.php';
?>

<div class="card text-center">
  <h3 class="mt-0">Affordable data & private proxies</h3>
  <p class="text-muted">Buy Safaricom data bundles instantly, or get residential proxy access — all paid via M-Pesa.</p>
</div>

<a href="/dataproxy/public/buy_data.php" class="btn">📶 Buy Data Bundle</a>
<br><br>
<a href="/dataproxy/public/buy_proxy.php" class="btn secondary">🌐 Buy Proxy Access</a>

<?php if (!$user): ?>
<div class="card" style="margin-top:20px;">
  <h3 class="mt-0">Have an account?</h3>
  <p class="text-muted">Log in to buy proxies, track orders, and top up your wallet for faster checkout.</p>
  <a href="/dataproxy/public/login.php" class="btn secondary">Log In</a>
  <br><br>
  <a href="/dataproxy/public/register.php" class="btn secondary">Create Account</a>
</div>
<?php endif; ?>

<div class="card" style="margin-top:20px;">
  <h3 class="mt-0">Had an issue with an order?</h3>
  <p class="text-muted">Look up your order and raise a complaint — no account needed.</p>
  <a href="/dataproxy/public/guest_lookup.php" class="btn secondary">Track / Report an Order</a>
</div>

<?php require __DIR__ . '/_footer.php'; ?>

<?php
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: /dataproxy/public/dashboard.php'); exit; }

$pageTitle = 'Log In';
$hasBottomNav = false;
require __DIR__ . '/_header.php';
?>

<h2>Log In</h2>

<?php if (isset($_GET['registered'])): ?>
  <div class="alert success">Account created! Log in to continue.</div>
<?php endif; ?>

<div id="alert" class="alert hidden"></div>

<form id="loginForm">
  <label for="phone">Phone Number</label>
  <input type="tel" id="phone" placeholder="07XXXXXXXX" required>

  <label for="password">Password</label>
  <input type="password" id="password" required>

  <button type="submit" class="btn" id="submitBtn">Log In</button>
</form>

<p class="text-center text-muted" style="margin-top:16px;">
  No account yet? <a href="/dataproxy/public/register.php">Create one</a>
</p>

<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert('alert');
  const btn = document.getElementById('submitBtn');
  setLoading(btn, true, 'Logging in...');

  const res = await apiPost('/dataproxy/api/login.php', {
    phone: document.getElementById('phone').value,
    password: document.getElementById('password').value,
  });

  setLoading(btn, false);

  if (res.success) {
    window.location.href = '/dataproxy/public/dashboard.php';
  } else {
    showAlert('alert', res.error || 'Login failed');
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>

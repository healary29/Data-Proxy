<?php
require_once __DIR__ . '/../includes/auth.php';
if (isLoggedIn()) { header('Location: /dataproxy/public/dashboard.php'); exit; }

$pageTitle = 'Create Account';
$hasBottomNav = false;
require __DIR__ . '/_header.php';
?>

<h2>Create Account</h2>
<p class="text-muted">You only need an account for proxy access, wallet top-ups, and order tracking. Data bundles can be bought as a guest too.</p>

<div id="alert" class="alert hidden"></div>

<form id="registerForm">
  <label for="phone">Phone Number</label>
  <input type="tel" id="phone" placeholder="07XXXXXXXX" required>

  <label for="email">Email (optional)</label>
  <input type="email" id="email" placeholder="you@example.com">

  <label for="password">Password</label>
  <input type="password" id="password" placeholder="At least 6 characters" required minlength="6">

  <button type="submit" class="btn" id="submitBtn">Create Account</button>
</form>

<p class="text-center text-muted" style="margin-top:16px;">
  Already have an account? <a href="/dataproxy/public/login.php">Log in</a>
</p>

<script>
document.getElementById('registerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  hideAlert('alert');
  const btn = document.getElementById('submitBtn');
  setLoading(btn, true, 'Creating account...');

  const res = await apiPost('/dataproxy/api/register.php', {
    phone: document.getElementById('phone').value,
    email: document.getElementById('email').value,
    password: document.getElementById('password').value,
  });

  setLoading(btn, false);

  if (res.success) {
    window.location.href = '/dataproxy/public/login.php?registered=1';
  } else {
    showAlert('alert', res.error || 'Registration failed');
  }
});
</script>

<?php require __DIR__ . '/_footer.php'; ?>

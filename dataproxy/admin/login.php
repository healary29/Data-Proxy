<?php
require_once __DIR__ . '/../includes/admin_auth.php';
if (currentAdmin()) { header('Location: /dataproxy/admin/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = adminLogin(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
    if ($result['success']) {
        header('Location: /dataproxy/admin/dashboard.php');
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — DataProxy</title>
  <link rel="stylesheet" href="/dataproxy/assets/css/style.css">
</head>
<body>
  <div class="container" style="max-width:360px; padding-top:60px;">
    <h2 class="text-center">DataProxy Admin</h2>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" required>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button type="submit" class="btn">Log In</button>
    </form>
  </div>
</body>
</html>

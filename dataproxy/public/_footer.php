<?php
// =========================================================
// _footer.php — include at bottom of every public page
// Expects $activeNav to be set (home|buy|proxy|tickets|account)
// =========================================================
$activeNav = $activeNav ?? '';
?>
  </div><!-- /.container -->

  <?php if ($hasBottomNav ?? true): ?>
  <div class="bottom-nav">
    <a href="/dataproxy/public/index.php" class="<?= $activeNav === 'home' ? 'active' : '' ?>">🏠<br>Home</a>
    <a href="/dataproxy/public/buy_data.php" class="<?= $activeNav === 'buy' ? 'active' : '' ?>">📶<br>Data</a>
    <a href="/dataproxy/public/buy_proxy.php" class="<?= $activeNav === 'proxy' ? 'active' : '' ?>">🌐<br>Proxy</a>
    <a href="/dataproxy/public/my_tickets.php" class="<?= $activeNav === 'tickets' ? 'active' : '' ?>">💬<br>Support</a>
    <a href="/dataproxy/public/dashboard.php" class="<?= $activeNav === 'account' ? 'active' : '' ?>">👤<br>Account</a>
  </div>
  <?php endif; ?>

  <script src="/dataproxy/assets/js/main.js"></script>
</body>
</html>

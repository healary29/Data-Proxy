<?php
// =========================================================
// api/logout.php
// =========================================================
require_once __DIR__ . '/../includes/auth.php';

logoutUser();
header('Location: /dataproxy/public/index.php');
exit;

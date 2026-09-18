<?php
require_once __DIR__ . '/../includes/admin_auth.php';
adminLogout();
header('Location: /dataproxy/admin/login.php');
exit;

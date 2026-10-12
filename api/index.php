<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/app.php';

header('Location: ' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
exit;

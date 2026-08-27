<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
requireAdmin();

header('Location: settings.php');
exit;

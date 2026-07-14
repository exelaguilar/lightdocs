<?php

declare(strict_types=1);

use Frontend\Startup;

if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/admin')) {
	require __DIR__ . '/admin/index.php';
	return;
}

define('APP_CONTEXT', 'public');

$config = require __DIR__ . '/system/startup.php';
Startup::run($config);

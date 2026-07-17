<?php

declare(strict_types=1);

if (str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/admin')) {
	require __DIR__ . '/admin/index.php';
	return;
}

define('APP_CONTEXT', 'frontend');

require __DIR__ . '/system/startup.php';
require __DIR__ . '/system/framework.php';

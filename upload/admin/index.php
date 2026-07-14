<?php

declare(strict_types=1);

use Admin\Startup;

define('APP_CONTEXT', 'admin');

// The front controller is also a valid direct request under PHP-FPM/Apache.
// Normalize it to the canonical OpenCart-style admin route before dispatch.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($request_path === '/admin/index.php' || $request_path === '/admin/index.php/') {
	$query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
	$_SERVER['REQUEST_URI'] = '/admin' . ($query !== null && $query !== '' ? '?' . $query : '');
}

$config = require dirname(__DIR__) . '/system/startup.php';
Startup::run($config);

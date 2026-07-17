<?php

declare(strict_types=1);

define('APP_CONTEXT', 'admin');

// The front controller is also a valid direct request under PHP-FPM/Apache.
// Normalize it to the canonical admin route before dispatch.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($request_path === '/admin/index.php' || $request_path === '/admin/index.php/') {
	$query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
	$_SERVER['REQUEST_URI'] = '/admin' . ($query !== null && $query !== '' ? '?' . $query : '');
}

require dirname(__DIR__) . '/system/startup.php';
require dirname(__DIR__) . '/system/framework.php';

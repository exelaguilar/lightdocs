<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$public_root = realpath(__DIR__);
$file = realpath(__DIR__ . $path);
$types = [
		'css' => 'text/css; charset=utf-8', 'js' => 'text/javascript; charset=utf-8',
		'json' => 'application/json; charset=utf-8', 'svg' => 'image/svg+xml',
		'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
		'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
		'html' => 'text/html; charset=utf-8',
];
$extension = $file !== false ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';
if ($path !== '/' && isset($types[$extension]) && $public_root !== false && $file !== false && str_starts_with(strtolower($file), strtolower($public_root . DIRECTORY_SEPARATOR)) && is_file($file)) {
	header('Content-Type: ' . $types[$extension]);
	header('Content-Length: ' . filesize($file));
	header('X-Content-Type-Options: nosniff');
	readfile($file);
	return;
}
require __DIR__ . '/index.php';

<?php

declare(strict_types=1);

use System\Engine\Action;

define('APP_CONTEXT', 'admin');

// The front controller is also a valid direct request under PHP-FPM/Apache.
// Normalize it to the canonical admin route before dispatch.
$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($request_path === '/admin/index.php' || $request_path === '/admin/index.php/') {
	$query = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
	$_SERVER['REQUEST_URI'] = '/admin' . ($query !== null && $query !== '' ? '?' . $query : '');
}

require dirname(__DIR__) . '/system/startup.php';
$registry = require dirname(__DIR__) . '/system/framework.php';

try {
	$config = $registry->get('config');
	$event = $registry->get('event');
	$request = $registry->get('request');
	$response = $registry->get('response');
	$error_action = new Action((string)$config->get('action_error', 'error/not_found'));

	foreach ((array)$config->get('pre_actions', []) as $route) {
		$pre_action = new Action((string)$route);
		$pre_args = [&$pre_action];
		$event->trigger('controller.pre_action.before', $pre_args);
		$pre_action->execute($registry, $pre_args);
		$event->trigger('controller.pre_action.after', $pre_args);
	}

	$route = (string)($request->get['route'] ?? $config->get('action_default', 'common/dashboard'));
	$route = preg_replace('/[^a-z0-9_\/\.\-]/i', '', $route);
	if (str_contains($route, '._')) {
		$route = (string)$config->get('action_error', 'error/not_found');
	}

	$trigger = $route;
	$args = [];
	$before_args = [&$trigger, &$args];
	$event->trigger("controller/{$trigger}/before", $before_args);
	$dispatch = new Action($trigger);
	$result = null;

	while (true) {
		try {
			$result = $dispatch->execute($registry, $args);
		} catch (Throwable $exception) {
			$registry->get('error_log')->error($exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
			$result = $error_action->execute($registry, [$exception]);
			break;
		}
		if (!$result instanceof Action) break;
		$dispatch = $result;
	}

	$after_args = [&$trigger, &$args, &$result];
	$event->trigger("controller/{$trigger}/after", $after_args);
	if (is_string($result) && $response->getOutput() === '') {
		$response->setOutput($result);
	}
	$response->output();
} catch (Throwable $exception) {
	$registry->get('error_log')->error($exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
	\System\Library\ErrorHandler::renderFallback('Something went wrong. It has been logged.');
	exit;
}

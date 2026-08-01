<?php

declare(strict_types=1);

use Lightdocs\Bootstrap\ContentSetup;
use Lightdocs\Bootstrap\CoreSetup;
use Lightdocs\Bootstrap\ExtensionSetup;
use Lightdocs\Bootstrap\TemplateSetup;
use System\Engine\Action;
use System\Engine\Front;
use System\Engine\Kernel;
use System\Library\ErrorHandler;

// Kernel owns the standard runtime. Providers own only Lightdocs composition;
// route selection and dispatch intentionally remain entry-point concerns.
try {
	$kernel = new Kernel(
		context: defined('APP_CONTEXT') ? APP_CONTEXT : 'frontend',
		applicationRoot: dirname(__DIR__) . DIRECTORY_SEPARATOR,
		providers: [
			CoreSetup::class,
			ContentSetup::class,
			ExtensionSetup::class,
			TemplateSetup::class,
		],
	);
	$registry = $kernel->boot();
} catch (Throwable $exception) {
	$environment = (string)($_ENV['LIGHTDOCS_ENV'] ?? $_SERVER['LIGHTDOCS_ENV'] ?? getenv('LIGHTDOCS_ENV') ?: 'production');
	$message = $environment === 'development'
		? $exception->getMessage()
		: 'The application could not be started. Check the application logs.';
	ErrorHandler::renderFallback($message);
	exit;
}
$config = $registry->get('config');
$event = $registry->get('event');
$request = $registry->get('request');
$response = $registry->get('response');

$front = new Front($registry);
$registry->set('front', $front);
$error_action = new Action($config->get('action_error', 'error/not_found'));
$main_action = null;

foreach ((array)$config->get('pre_actions', []) as $action_route) {
	$pre_action = new Action($action_route);
	$event_args = [&$pre_action];
	$event->trigger('controller.pre_action.before', $event_args);
	$result = $pre_action->execute($registry, $event_args);
	$event->trigger('controller.pre_action.after', $event_args);
	if ($result instanceof Action) {
		$main_action = $result;
		break;
	}
	if ($result instanceof Throwable) {
		$main_action = $error_action;
		break;
	}
}

if (!$main_action) {
	$route = (string)($request->get['route'] ?? $config->get('action_default', 'common/dashboard'));
	$route = preg_replace('/[^a-z0-9_\/\.\-]/i', '', $route);
	if (strpos($route, '._') !== false) {
		$route = (string)$config->get('action_error', 'error/not_found');
	}
	$main_action = new Action($route);
}

$front->dispatch($main_action, $error_action);
$response->output();

<?php

use System\Bootstrap\ContentSetup;
use System\Bootstrap\CoreSetup;
use System\Bootstrap\ExtensionSetup;
use System\Bootstrap\TemplateSetup;
use System\Engine\Action;
use System\Engine\Front;
use System\Engine\Kernel;
use System\Engine\Provider;

// TinyMVC owns deterministic base boot and provider sequencing. Lightdocs owns
// the provider list, application policy, and final route dispatch.
$kernel = new Kernel(
	context: defined('APP_CONTEXT') ? APP_CONTEXT : 'frontend',
	systemRoot: DIR_SYSTEM,
	applicationRoot: DIR_ROOT,
	enforceApplicationConstants: true,
);
$registry = $kernel->boot();
$config = $registry->get('config');

(new Provider($registry, [
	new CoreSetup(),
	new TemplateSetup(),
	new ExtensionSetup(),
	new ContentSetup(),
]))->boot();

$request = $registry->get('request');
$response = $registry->get('response');
$event = $registry->get('event');
$front = new Front($registry);
$registry->set('front', $front);

$error_action = new Action($config->get('action_error', 'error/not_found'));
$main_action = null;

// Pre-actions remain visible in the composition root because they control the
// request pipeline, rather than registering reusable application services.
foreach ($config->get('pre_actions', []) as $action_route) {
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
	$route = preg_replace('/[^a-z0-9_\/\.-]/i', '', $route);
	if (strpos($route, '._') !== false) {
		$route = (string)$config->get('action_error', 'error/not_found');
	}
	$main_action = new Action($route);
}

$front->dispatch($main_action, $error_action);
$response->output();

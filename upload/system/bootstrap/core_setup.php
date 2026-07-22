<?php

declare(strict_types=1);

namespace System\Bootstrap;

use System\Engine\Action;
use System\Engine\Event;
use System\Engine\Factory;
use System\Engine\Loader;
use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Library\AssetPublisher;
use System\Library\Db\SqliteDb;
use System\Library\Document;
use System\Library\ErrorHandler;
use System\Library\FileCache;
use System\Library\JobQueue;
use System\Library\Language;
use System\Library\Log;
use System\Library\Request;
use System\Library\Response;
use System\Library\Template;
use System\Library\Url;
use System\Model\Schema;

/** Registers the reusable HTTP/runtime services required by every Lightdocs context. */
final class CoreSetup implements Contract
{
	public function register(Registry $registry): void
	{
		$config = $registry->get('config');
		$db = new SqliteDb($config->get('database_path'));
		$registry->set('db', $db);

		$error_log = new Log(DIR_LOGS . $config->get('error_file'), $config);
		$registry->set('error_log', $error_log);
		ErrorHandler::install($config, $error_log);
		$registry->set('debug_log', new Log(DIR_LOGS . $config->get('debug_file'), $config));
		$registry->set('cache', new FileCache($config->get('cache_dir')));

		$template = new Template($config->get('template_engine'), $config);
		$template->addPath(DIR_TEMPLATE);
		$registry->set('template', $template);
		$language = new Language($config->get('language', 'en'));
		$language->addPath(DIR_LANGUAGE);
		$language->load('default');
		$registry->set('language', $language);

		$event = new Event($registry);
		$registry->set('event', $event);
		$request = new Request();
		$response = new Response();
		$response->setRequest($request);
		$registry->set('request', $request);
		$registry->set('response', $response);
		SessionSetup::register($registry, $config, $request);

		$csp_nonce = bin2hex(random_bytes(16));
		$config->set('csp_nonce', $csp_nonce);
		$template->addGlobal('csp_nonce', $csp_nonce);
		$response->addHeader('Content-Type: text/html; charset=utf-8');
		$response->addHeader("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$csp_nonce}'; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'");
		$response->addHeader('X-Content-Type-Options: nosniff');
		$response->addHeader('Referrer-Policy: strict-origin-when-cross-origin');
		$response->setCompression((int)$config->get('response_compression'));

		$base_url = (string)$config->get('base_url');
		$registry->set('url', new Url($base_url !== '' ? $base_url . '/' : '/', (array)$config->get('routes', [])));
		$registry->set('document', new Document($config));
		$asset_publisher = new AssetPublisher(
			(string)$config->get('asset_public_root'),
			(string)$config->get('asset_public_base'),
			(bool)$config->get('asset_read_only', false),
			2,
			(string)$config->get('asset_state_root'),
		);
		$registry->set('asset_publisher', $asset_publisher);
		$config->set('published_assets', $asset_publisher->manifest()['assets'] ?? []);
		$registry->set('factory', new Factory($registry));
		$registry->set('load', new Loader($registry));
		(new Schema($registry))->migrate();
		$registry->set('job_queue', new JobQueue($db));
	}

	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$event = $registry->get('event');
		foreach ((array)$config->get('action_event', []) as $trigger => $listeners) {
			foreach ((array)$listeners as $sort_order => $route) {
				$event->register((string)$trigger, new Action((string)$route), (int)$sort_order, 'config.' . str_replace('/', '.', (string)$route), 'config');
			}
		}
	}
}

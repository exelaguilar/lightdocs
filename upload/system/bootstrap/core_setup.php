<?php

declare(strict_types=1);

namespace Lightdocs\Bootstrap;

use System\Engine\Action;
use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Library\AssetPublisher;
use System\Model\Schema;

final class CoreSetup implements Contract
{
	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$event = $registry->get('event');
		$response = $registry->get('response');

		$response->setCompression((int)$config->get('response_compression', 0));
		foreach ((array)$config->get('action_event', []) as $trigger => $actions) {
			foreach ((array)$actions as $sort_order => $route) {
				$event->register($trigger, new Action((string)$route), (int)$sort_order);
			}
		}

		$asset_publisher = new AssetPublisher(
			(string)$config->get('asset_public_root'),
			(string)$config->get('asset_public_base'),
			(bool)$config->get('asset_read_only', false),
			2,
			(string)$config->get('asset_state_root'),
		);
		$registry->set('asset_publisher', $asset_publisher);
		$manifest = $asset_publisher->manifest();
		$config->set('published_assets', $manifest['assets'] ?? []);

		(new Schema($registry))->migrate();

	}
}

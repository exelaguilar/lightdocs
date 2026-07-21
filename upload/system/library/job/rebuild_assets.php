<?php

declare(strict_types=1);

namespace System\Library\Job;

use LogicException;
use System\Engine\Config;
use System\Engine\Registry;
use System\Library\Service\CssBuilder;

final class RebuildAssets implements JobHandlerInterface
{
	private CssBuilder $builder;
	private Config $config;

	public function __construct(Registry $registry)
	{
		$builder = $registry->get('css');
		$config = $registry->get('config');
		if (!$builder instanceof CssBuilder || !$config instanceof Config) {
			throw new LogicException('The asset rebuild handler requires the CSS builder and application config.');
		}

		$this->builder = $builder;
		$this->config = $config;
	}

	public function handle(array $payload, callable $heartbeat): void
	{
		if ((bool)$this->config->get('asset_read_only', false)) {
			throw new NonRetryableException('Runtime asset publication is disabled.');
		}

		$heartbeat();
		$this->builder->build();
		$heartbeat();
	}
}

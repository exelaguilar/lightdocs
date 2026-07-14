<?php

declare(strict_types=1);

namespace Admin;

use System\Framework;

final class Startup
{
	public static function run(array $config): void
	{
		(new Framework($config, 'admin'))->run();
	}
}

<?php

declare(strict_types=1);

namespace Frontend;

use System\Framework;

final class Startup
{
	public static function run(array $config): void
	{
		(new Framework($config, 'public'))->run();
	}
}

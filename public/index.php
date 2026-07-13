<?php

declare(strict_types=1);

use Lightdocs\App\Framework;

$config = require dirname(__DIR__) . '/bootstrap.php';
(new Framework($config))->run();

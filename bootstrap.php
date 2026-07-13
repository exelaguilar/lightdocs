<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$root = __DIR__;
require $root . '/vendor/autoload.php';

// Local development uses .env in the project. Packaged installations can keep
// their environment file in /etc (or any other persistent location) while the
// application release remains immutable. Real process environment values win.
$environmentFile = getenv('LIGHTDOCS_ENV_FILE');
$environmentFile = $environmentFile !== false && trim($environmentFile) !== ''
    ? trim($environmentFile)
    : $root . '/.env';
$environmentDirectory = dirname($environmentFile);
$environmentName = basename($environmentFile);
if (is_dir($environmentDirectory)) {
    Dotenv::createImmutable($environmentDirectory, $environmentName)->safeLoad();
}

return require $root . '/config/app.php';

<?php

declare(strict_types=1);

define('APP_CONTEXT', 'frontend');

require dirname(__DIR__) . '/upload/system/startup.php';

$kernel = new \System\Engine\Kernel(
    context: APP_CONTEXT,
    systemRoot: DIR_SYSTEM,
    applicationRoot: DIR_ROOT,
    localConfigFile: null,
    enforceApplicationConstants: true,
);
$registry = $kernel->boot();
$config = $registry->get('config');

$publisher = new System\Library\AssetPublisher(
    (string)$config->get('asset_public_root'),
    (string)$config->get('asset_public_base'),
    false,
    2,
    (string)$config->get('asset_state_root'),
);
$bytes = (new System\Library\Service\CssBuilder($config->all(), $publisher))->build();

fwrite(STDOUT, sprintf("Wrote admin and frontend stylesheets (%d bytes total)\n", $bytes));

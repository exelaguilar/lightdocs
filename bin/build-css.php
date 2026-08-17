<?php

declare(strict_types=1);

/**
 * Builds the published admin and frontend CSS assets.
 *
 * This tool needs configuration and asset services, but does not dispatch an
 * HTTP request or compose the full Lightdocs application.
 */
define('APP_CONTEXT', 'frontend');

require dirname(__DIR__) . '/upload/system/startup.php';

$bootstrap = new \System\Engine\Bootstrap(
    context: APP_CONTEXT,
    application_root: DIR_ROOT,
    local_config_file: null,
);
$registry = $bootstrap->boot();
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

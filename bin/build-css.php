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

$bytes = (new System\Library\Service\CssBuilder($config->all()))->build();

fwrite(STDOUT, sprintf("Wrote admin and frontend stylesheets (%d bytes total)\n", $bytes));

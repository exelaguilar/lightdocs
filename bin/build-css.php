<?php

declare(strict_types=1);

define('APP_CONTEXT', 'frontend');

require dirname(__DIR__) . '/upload/system/startup.php';

$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);

$config = new \System\Engine\Config();
$config->load('default.php');
$config->load('frontend.php');

$bytes = (new System\Library\Service\CssBuilder($config->all()))->build();

fwrite(STDOUT, sprintf("Wrote admin and frontend stylesheets (%d bytes total)\n", $bytes));

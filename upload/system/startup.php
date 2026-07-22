<?php
// system/startup.php

$web_root = dirname(__DIR__);
$project_root = dirname($web_root);

// System Version
define('SYSTEM_VERSION', is_file($project_root . '/VERSION') ? trim((string)file_get_contents($project_root . '/VERSION')) : 'development');

// Error Reporting
error_reporting(E_ALL);

// Check Version
if (version_compare(PHP_VERSION, '8.4', '<')) {
    exit('PHP 8.4+ Required');
}

if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', $web_root . DIRECTORY_SEPARATOR);
}

if (!defined('DIR_SYSTEM')) {
    define('DIR_SYSTEM', DIR_ROOT . 'system' . DIRECTORY_SEPARATOR);
}

if (!defined('DIR_PROJECT')) {
    define('DIR_PROJECT', $project_root . DIRECTORY_SEPARATOR);
}

// Composer vendor autoload + third-party dependencies. The framework
// Autoloader class now resolves through Composer's classmap like every other
// package class, so no direct engine/autoloader.php require remains.
require_once(DIR_SYSTEM . 'vendor.php');

use Dotenv\Dotenv;

// Local development uses .env in the project. Packaged installations can keep
// their environment file in /etc (or any other persistent location) while the
// application release remains immutable. Real process environment values win.
$environment_file = getenv('LIGHTDOCS_ENV_FILE');
$environment_file = $environment_file !== false && trim($environment_file) !== ''
    ? trim($environment_file)
    : DIR_PROJECT . '.env';
$environment_directory = dirname($environment_file);
$environment_name = basename($environment_file);
if (is_dir($environment_directory)) {
    Dotenv::createImmutable($environment_directory, $environment_name)->safeLoad();
}

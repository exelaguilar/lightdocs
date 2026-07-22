<?php

declare(strict_types=1);

require dirname(__DIR__) . '/upload/system/startup.php';
require_once DIR_SYSTEM . 'engine/lightdocs/extension/authorization.php';

use System\Engine\Lightdocs\Extension\Authorization;
use System\Engine\Extension\Manifest;
use System\Engine\Registry;
use System\Library\User;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new \RuntimeException($message);
};
$manifest = Manifest::fromFile(DIR_ROOT . 'extension/audit/extension.json');

$registry = new Registry();
$authorization = new Authorization($registry);
try {
    $authorization->assertAuthorized('install', $manifest);
    $assert(false, 'Missing user was authorized.');
} catch (\RuntimeException $exception) {
    $assert(str_contains($exception->getMessage(), 'not authorized'), 'Missing user did not fail closed.');
}

$denied = new class extends User {
    public function __construct() {}
    public function isLogged(): bool { return true; }
    public function hasPermission(string $type, string $route): bool { return false; }
};
$registry->set('user', $denied);
try {
    $authorization->assertAuthorized('enable', $manifest);
    $assert(false, 'User without modify permission was authorized.');
} catch (\RuntimeException $exception) {
    $assert(str_contains($exception->getMessage(), 'enable'), 'Denied operation was not identified.');
}

$allowed = new class extends User {
    /** @var list<string> */
    public array $decision = [];
    public function __construct() {}
    public function isLogged(): bool { return true; }
    public function hasPermission(string $type, string $route): bool
    {
        $this->decision = [$type, $route];
        return true;
    }
};
$registry->set('user', $allowed);
$authorization->assertAuthorized('upgrade', $manifest);
$assert($allowed->decision === ['modify', 'tools/extensions'], 'Lifecycle operation did not map to the Lightdocs ACL route.');

fwrite(STDOUT, "Extension authorization: {$assertions}/{$assertions} assertions passed.\n");

<?php

declare(strict_types=1);

use System\Engine\ExtensionDiscovery;
use System\Engine\ExtensionManifest;
use System\Engine\ExtensionContext;
use System\Library\User;

require dirname(__DIR__) . '/upload/system/startup.php';
require_once DIR_SYSTEM . 'library/extension_state.php';

$failures = [];
$passes = 0;

$check = static function (bool $condition, string $message) use (&$failures, &$passes): void {
	if ($condition) {
		$passes++;
		fwrite(STDOUT, '[PASS] ' . $message . PHP_EOL);
		return;
	}
	$failures[] = $message;
	fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
};

$manifests = (new ExtensionDiscovery(DIR_ROOT . 'extension'))->discover();
$expected = ['audit', 'backup', 'local_git', 'mail', 'media', 'reader_banner', 'remote_sync', 'storage', 'webhooks'];

$check(array_keys($manifests) === $expected, 'all nine bundled manifests are discovered deterministically');
$check(count(array_filter($manifests, static fn ($manifest): bool => $manifest instanceof ExtensionManifest)) === 9, 'all bundled manifests use the package value object');
$check(count(array_filter($manifests, static fn (ExtensionManifest $manifest): bool => $manifest->schemaVersion() === 3)) === 9, 'all bundled manifests declare schema version 3');
$state = new ReflectionClass(System\Library\ExtensionState::class);
$check(!$state->isInterface() && $state->isFinal(), 'Lightdocs owns concrete extension installation persistence');
$check((new ReflectionMethod(System\Engine\ExtensionInterface::class, 'register'))->getParameters()[0]->getType()?->getName() === ExtensionContext::class, 'portable extensions receive the package-owned runtime context');

$interfaceFile = (new ReflectionClass(System\Engine\ExtensionInterface::class))->getFileName();
$check(
	is_string($interfaceFile) && str_contains(str_replace('\\', '/', $interfaceFile), '/vendor/exelaguilar/tiny-mvc-framework-private/system/engine/extension_interface.php'),
	'extension interface resolves from TinyMVC rather than a local duplicate'
);

$userFile = (new ReflectionClass(User::class))->getFileName();
$check(
	is_string($userFile) && str_contains(str_replace('\\', '/', $userFile), '/vendor/exelaguilar/tiny-mvc-framework-private/system/library/user.php'),
	'current-user principal resolves from TinyMVC rather than a local duplicate'
);
$check(!method_exists(User::class, 'isProtectedAdminUser') && !is_file(DIR_SYSTEM . 'library/user.php'), 'database-backed user protection policy remains application-owned');

if ($failures !== []) {
	fwrite(STDERR, sprintf('Extension platform: %d/8 passed, %d failed.%s', $passes, count($failures), PHP_EOL));
	exit(1);
}

fwrite(STDOUT, 'Extension platform: 8/8 passed, 0 failed.' . PHP_EOL);

<?php

declare(strict_types=1);

/** Verifies the v0.10 boundary: runtime/mechanics are vendored; policy is local. */

require dirname(__DIR__) . '/upload/system/startup.php';

$packagePath = Composer\InstalledVersions::getInstallPath('exelaguilar/tiny-mvc-framework-private');
if ($packagePath === null || ($packageRoot = realpath($packagePath)) === false) {
	fwrite(STDERR, "TinyMVC is not installed through Composer.\n");
	exit(1);
}

$failures = [];
$classes = [];
$localAutoloader = new System\Engine\Autoloader();
$localAutoloader->register('System', DIR_SYSTEM);
$systemRoot = $packageRoot . DIRECTORY_SEPARATOR . 'system';
$composerClassmap = require DIR_ROOT . 'vendor/composer/autoload_classmap.php';
foreach ($composerClassmap as $class => $file) {
	$realFile = realpath($file);
	if ($realFile !== false && str_starts_with($realFile, $systemRoot . DIRECTORY_SEPARATOR)) $classes[$class] = $realFile;
}

foreach ($classes as $class => $expectedFile) {
	if (!class_exists($class, true) && !interface_exists($class, true)) {
		$failures[] = $class . ' did not autoload.';
		continue;
	}
	$actual = (new ReflectionClass($class))->getFileName();
	if ($actual === false || realpath($actual) !== realpath($expectedFile)) $failures[] = $class . ' did not resolve from the installed package.';
}

$localClasses = [
	System\Engine\Lightdocs\Extension\Manager::class,
	System\Engine\Lightdocs\Extension\Compatibility::class,
	System\Engine\Lightdocs\Extension\DependencyResolver::class,
	System\Engine\Lightdocs\Extension\PackageTrust::class,
	System\Engine\Lightdocs\Extension\Authorization::class,
	System\Library\ExtensionState::class,
];
foreach ($localClasses as $class) {
	if (!class_exists($class, true)) {
		$failures[] = $class . ' did not autoload from Lightdocs.';
		continue;
	}
	$file = (new ReflectionClass($class))->getFileName();
	if ($file === false || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', DIR_SYSTEM))) $failures[] = $class . ' did not resolve from Lightdocs.';
}

$removed = [
	'System\Engine\Manager',
	'System\Engine\InstallationRepositoryInterface',
	'System\Engine\ExtensionOperationAuthorizerInterface',
	'System\Engine\AllowAllExtensionOperationAuthorizer',
	'System\Engine\InMemoryInstallationRepository',
];
foreach ($removed as $class) {
	if (isset($classes[$class])) $failures[] = $class . ' remains in the TinyMVC package.';
}

if ($failures !== []) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

printf("Package boundary: %d framework classes resolve from TinyMVC; %d policy classes resolve from Lightdocs.\n", count($classes), count($localClasses));

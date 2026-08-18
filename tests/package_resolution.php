<?php

declare(strict_types=1);

/** Verifies that generic extension mechanics resolve from the TinyMVC package. */

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

$frameworkClasses = [
	System\Engine\ExtensionManager::class,
	System\Engine\ExtensionInstallation::class,
	System\Library\ExtensionState::class,
];

foreach ($frameworkClasses as $class) {
	if (!class_exists($class, true)) {
		$failures[] = $class . ' did not autoload from TinyMVC.';
		continue;
	}
	$file = (new ReflectionClass($class))->getFileName();
	if ($file === false || !str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', $systemRoot))) $failures[] = $class . ' did not resolve from TinyMVC.';
}

$removed = [
	'System\\Engine\\ExtensionAuthorization',
	'System\\Engine\\ExtensionPackageTrust',
	'System\\Engine\\ExtensionCatalog',
	'System\\Engine\\ExtensionCatalogEntry',
	'System\\Engine\\ExtensionPackageProof',
	'System\\Engine\\ExtensionInstallationRepositoryInterface',
	'System\\Engine\\ExtensionOperationAuthorizerInterface',
	'System\\Engine\\AllowAllExtensionOperationAuthorizer',
	'System\\Engine\\InMemoryExtensionInstallationRepository',
];
foreach ($removed as $class) {
	if (isset($classes[$class])) $failures[] = $class . ' remains in the TinyMVC package.';
}

if ($failures !== []) {
	fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
	exit(1);
}

printf("Package boundary: %d framework classes resolve from TinyMVC.\n", count($classes));

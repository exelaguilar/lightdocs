<?php

declare(strict_types=1);

require dirname(__DIR__) . '/upload/system/startup.php';
require __DIR__ . '/support/test_suite.php';

use Lightdocs\Tests\Support\TestSuite;
use System\Engine\ExtensionInstallation;
use System\Engine\Extension\Manifest;
use System\Engine\ExtensionManager;
use System\Engine\Registry;
use System\Library\Extension\PackageInstaller;

$autoloader = new System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);
$suite = new TestSuite('Lightdocs extension policy');

$manifest = static fn (string $version = '1.0.0', array $requires = []): Manifest => Manifest::fromArray([
	'schema_version' => 3,
	'name' => 'policy_fixture',
	'class' => 'Fixture\\Policy\\Extension',
	'version' => $version,
	'description' => 'Policy fixture.',
	'requires' => $requires,
]);

$suite->test('framework manager owns generic extension discovery', static function (): void {
	$directory = sys_get_temp_dir() . '/lightdocs-policy-' . bin2hex(random_bytes(4));
	mkdir($directory, 0700, true);
	$registry = new Registry();
	$registry->set('app', 'frontend');
	$manager = new ExtensionManager($registry, $directory);
	$runtime = $manager->boot('frontend');
	TestSuite::assertSame([], $manager->catalog(), 'An empty extension directory should have an empty catalog.');
	TestSuite::assertSame([], $runtime->names(), 'An empty extension directory should build an empty runtime.');
	rmdir($directory);
});

$suite->test('package installer still rejects unsigned packages when trust is configured', static function (): void {
	$root = sys_get_temp_dir() . '/lightdocs-policy-' . bin2hex(random_bytes(4));
	mkdir($root, 0700, true);
	$archive = $root . '/package.zip';
	$zip = new ZipArchive();
	$zip->open($archive, ZipArchive::CREATE);
	$zip->addFromString('extension.json', json_encode(['schema_version' => 3, 'name' => 'policy_fixture', 'class' => 'Fixture\\Policy\\Extension', 'version' => '1.0.0', 'description' => 'Policy fixture.'], JSON_THROW_ON_ERROR));
	$zip->close();
	$installer = new PackageInstaller($root . '/extensions', trusted_keys: ['configured-public-key']);
	$prepared = $installer->prepare($archive);
	try {
		try {
			$installer->install($prepared);
			TestSuite::assertTrue(false, 'Unsigned package was accepted despite configured trust.');
		} catch (RuntimeException $exception) {
			TestSuite::assertTrue(str_contains($exception->getMessage(), 'signature'), 'Trust failure did not mention the missing signature.');
		}
	} finally {
		$prepared->cleanup();
		if (is_dir($root)) \System\Helper\Filesystem::removeTree($root);
	}
});

$suite->test('concrete installation state retains lifecycle transitions', static function () use ($manifest): void {
	$installation = ExtensionInstallation::bundled($manifest());
	$enabled = $installation->withState(ExtensionInstallation::ENABLED, true);
	TestSuite::assertTrue($enabled->enabled(), 'Enabled state was not retained.');
	TestSuite::assertSame(ExtensionInstallation::ENABLED, $enabled->status(), 'Lifecycle status changed.');
});

exit($suite->finish());

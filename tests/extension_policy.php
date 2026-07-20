<?php

declare(strict_types=1);

require dirname(__DIR__) . '/upload/system/startup.php';
require __DIR__ . '/support/test_suite.php';

use Lightdocs\Tests\Support\TestSuite;
use System\Engine\ExtensionCatalog;
use System\Engine\ExtensionCatalogEntry;
use System\Engine\ExtensionCompatibility;
use System\Engine\ExtensionInstallation;
use System\Engine\ExtensionManifest;
use System\Engine\ExtensionPackageProof;
use System\Engine\ExtensionPackageTrust;

$autoloader = new System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);
$suite = new TestSuite('Lightdocs extension policy');

$manifest = static fn (string $version = '1.0.0', array $requires = []): ExtensionManifest => ExtensionManifest::fromArray([
	'schema_version' => 3,
	'name' => 'policy_fixture',
	'class' => 'Fixture\\Policy\\Extension',
	'version' => $version,
	'description' => 'Policy fixture.',
	'requires' => $requires,
]);

$suite->test('compatibility policy accepts and rejects application platform versions', static function () use ($manifest): void {
	$policy = new ExtensionCompatibility();
	TestSuite::assertSame([], $policy->evaluate($manifest('1.0.0', ['php' => '>=8.4', 'tinymvc' => '^0.10']), ['php' => PHP_VERSION, 'tinymvc' => '0.10.0']), 'Compatible manifest was rejected.');
	TestSuite::assertTrue($policy->evaluate($manifest('1.0.0', ['tinymvc' => '^0.9']), ['tinymvc' => '0.10.0']) !== [], 'Incompatible manifest was accepted.');
});

$suite->test('catalog update selection remains application-owned and deterministic', static function (): void {
	$catalog = new ExtensionCatalog([
		new ExtensionCatalogEntry('demo', '1.1.0', 'stable', 'https://extensions.example/demo-1.1.0.zip', str_repeat('a', 64)),
		new ExtensionCatalogEntry('demo', '2.0.0', 'beta', 'https://extensions.example/demo-2.0.0.zip', str_repeat('b', 64)),
	]);
	$installed = new ExtensionInstallation('demo', '1.0.0', 'uploaded', ExtensionInstallation::ENABLED, true);
	TestSuite::assertSame('1.1.0', $catalog->updateFor($installed)?->version(), 'Stable catalog selection changed.');
});

$suite->test('signature trust delegates verification without a framework interface', static function (): void {
	$called = false;
	$trust = new ExtensionPackageTrust(ExtensionPackageTrust::REQUIRE_SIGNATURE, ['release' => 'public-key'], static function (string $hash, string $signature, string $key, string $algorithm) use (&$called): bool {
		$called = $hash === str_repeat('c', 64) && $signature === base64_encode('signature') && $key === 'public-key' && $algorithm === 'openssl-sha256';
		return $called;
	});
	$trust->assertTrusted(str_repeat('c', 64), new ExtensionPackageProof('release', 'openssl-sha256', base64_encode('signature')));
	TestSuite::assertTrue($called, 'Application trust verifier was not called with the proof data.');
});

$suite->test('concrete installation state retains lifecycle transitions', static function () use ($manifest): void {
	$installation = ExtensionInstallation::bundled($manifest());
	$enabled = $installation->withState(ExtensionInstallation::ENABLED, true);
	TestSuite::assertTrue($enabled->enabled(), 'Enabled state was not retained.');
	TestSuite::assertSame(ExtensionInstallation::ENABLED, $enabled->status(), 'Lifecycle status changed.');
});

exit($suite->finish());

<?php

declare(strict_types=1);

require dirname(__DIR__) . '/upload/system/startup.php';
require __DIR__ . '/support/test_suite.php';
require __DIR__ . '/support/temporary_directory.php';

use Lightdocs\Tests\Support\TemporaryDirectory;
use Lightdocs\Tests\Support\TestSuite;
use System\Engine\Autoloader;
use System\Engine\Config;
use System\Engine\Extension\Discovery;
use System\Engine\Extension\ResourceMountApplier;
use System\Engine\Extension\RuntimeBuilder;
use System\Engine\Lightdocs\Extension\Manager;
use System\Engine\Registry;
use System\Library\Db\SqliteDb;
use System\Library\Extension\PackageInstaller;
use System\Library\ExtensionState;

$application_autoloader = new Autoloader();
$application_autoloader->register('System', DIR_SYSTEM);
$suite = new TestSuite('Lightdocs extension package lifecycle');

$suite->test('install, enable, boot, upgrade, disable, and remove use real package and database state', static function (): void {
	$temporary = new TemporaryDirectory('lightdocs-extension-lifecycle-');
	$extension_root = $temporary->path . '/extension';
	mkdir($extension_root, 0700, true);
	$database = new SqliteDb($temporary->path . '/state.sqlite');
	$database->connection()->exec(<<<'SQL'
CREATE TABLE extensions (
	name TEXT PRIMARY KEY, version TEXT NOT NULL, source TEXT NOT NULL,
	status TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 0,
	package_hash TEXT NOT NULL DEFAULT '', discovered_at INTEGER NOT NULL DEFAULT 0,
	installed_at INTEGER NOT NULL DEFAULT 0, updated_at INTEGER NOT NULL DEFAULT 0,
	error TEXT
);
CREATE TABLE extension_settings (
	extension TEXT NOT NULL, setting_key TEXT NOT NULL, value_json TEXT NOT NULL,
	updated_at INTEGER NOT NULL, PRIMARY KEY (extension, setting_key)
);
SQL);
	$state = new ExtensionState($database);
	$packages = new PackageInstaller($extension_root);
	$archive = static function (string $version) use ($temporary): string {
		$path = $temporary->path . '/fixture-' . str_replace('.', '-', $version) . '.zip';
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->addFromString('extension.json', json_encode([
			'schema_version' => 3,
			'name' => 'lifecycle_fixture',
			'class' => 'Fixture\\Lifecycle\\Extension',
			'version' => $version,
			'description' => 'Real lifecycle fixture.',
			'contexts' => ['admin'],
			'requires' => ['php' => '>=8.4', 'tinymvc' => '^0.32'],
			'resources' => ['namespaces' => ['Fixture\\Lifecycle' => 'src/']],
		], JSON_THROW_ON_ERROR));
		$zip->addFromString('src/extension.php', <<<'PHP'
<?php
namespace Fixture\Lifecycle;

use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;

final class Extension implements Contract
{
	public function register(Context $context): void
	{
		$context->service('fixture.version', $context->manifest()->version());
	}
}
PHP);
		$zip->close();

		return $path;
	};
	$manager = static function () use ($extension_root, $state, $packages): Manager {
		$registry = new Registry();
		$registry->set('autoloader', new Autoloader());
		$registry->set('config', new Config(dirname($extension_root)));
		return new Manager(
			new Discovery($extension_root),
			$state,
			platformVersions: ['php' => PHP_VERSION, 'tinymvc' => '0.32.0'],
			autoloader: $registry->get('autoloader'),
			packages: $packages,
			runtimeBuilder: new RuntimeBuilder($registry, new ResourceMountApplier($registry)),
		);
	};

	$first = $manager();
	$installed = $first->installArchive($archive('1.0.0'));
	TestSuite::assertSame('installed_disabled', $installed->status(), 'Install did not create disabled package state.');
	$first->setEnabled('lifecycle_fixture', true);
	TestSuite::assertSame('1.0.0', $first->boot('admin')->get('fixture.version'), 'Enabled package did not boot through TinyMVC RuntimeBuilder.');

	$upgraded = $first->upgradeArchive('lifecycle_fixture', $archive('1.1.0'));
	TestSuite::assertSame('1.1.0', $upgraded->version(), 'Upgrade did not persist the new version.');
	$second = $manager();
	TestSuite::assertSame('1.1.0', $second->boot('admin')->get('fixture.version'), 'Upgraded manifest did not reach the runtime context.');
	$second->setEnabled('lifecycle_fixture', false);
	TestSuite::assertTrue(!$state->find('lifecycle_fixture')->enabled(), 'Disable did not persist application state.');
	$second->uninstall('lifecycle_fixture');
	TestSuite::assertSame(null, $state->find('lifecycle_fixture'), 'Uninstall did not remove database state.');
	TestSuite::assertTrue(!is_dir($extension_root . '/lifecycle_fixture'), 'Uninstall did not remove package files.');
});

exit($suite->finish());

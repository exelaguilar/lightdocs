<?php

declare(strict_types=1);

namespace System\Bootstrap;

use Composer\InstalledVersions;
use System\Engine\Extension\Discovery;
use System\Engine\Extension\Manifest;
use System\Engine\Extension\ResourceMountApplier;
use System\Engine\Extension\RuntimeBuilder;
use System\Engine\Lightdocs\Extension\Administration;
use System\Engine\Lightdocs\Extension\Application;
use System\Engine\Lightdocs\Extension\Authorization;
use System\Engine\Lightdocs\Extension\CapabilityRegistry;
use System\Engine\Lightdocs\Extension\Manager;
use System\Engine\Lightdocs\Extension\PackageTrust;
use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Engine\Startup;
use System\Library\Extension\PackageInstaller;
use System\Library\ExtensionState;

final class ExtensionSetup implements Contract
{
	public function register(Registry $registry): void
	{
	}

	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$extension_state = new ExtensionState($registry->get('db'));
		$extension_startups = new Startup();
		$extension_capabilities = new CapabilityRegistry();
		$extension_capabilities->register('lightdocs.application', static function (Manifest $manifest) use ($registry, $config, $extension_state, $extension_startups): Application {
			return new Application(
				$manifest->name(),
				$config->all(),
				$registry->get('repository'),
				$registry->get('directives'),
				$registry->get('db'),
				$extension_state->settings($manifest->name()),
				$extension_startups,
			);
		});
		$runtime_builder = new RuntimeBuilder($registry, new ResourceMountApplier($registry));
		$extension_manager = new Manager(
			new Discovery($config->get('extension_dir')),
			$extension_state,
			capabilities: $extension_capabilities,
			platformVersions: [
				'php' => PHP_VERSION,
				'tinymvc' => InstalledVersions::getPrettyVersion('exelaguilar/tiny-mvc-framework-private') ?: '0.32.0',
			],
			autoloader: $registry->get('autoloader'),
			packages: new PackageInstaller($config->get('extension_dir')),
			authorizer: new Authorization($registry),
			trust: new PackageTrust((string)$config->get('extension_trust_mode'), (array)$config->get('extension_trusted_signers')),
			runtimeBuilder: $runtime_builder,
		);
		$extension_manager->recover();
		$extension_runtime = $extension_manager->boot(APP_CONTEXT === 'frontend' ? 'public' : (string)APP_CONTEXT);
		$extensions = new Administration($extension_manager, $extension_runtime, $extension_state, $extension_startups);
		$extensions->registerEvents($registry->get('event'));
		$extensions->runStartups($registry->get('event'));
		$registry->set('extensions', $extensions);

		$services = $extensions->services();
		$registry->set('git_history', $services['local_git.history'] ?? null);
		$registry->set('git_preflight', $services['local_git.preflight'] ?? null);
		$config->set('admin_navigation', $extensions->navigationItems());
		$config->set('extension_assets', $extensions->assets());
	}
}

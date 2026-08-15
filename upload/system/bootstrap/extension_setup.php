<?php

declare(strict_types=1);

namespace Lightdocs\Bootstrap;

use System\Engine\ExtensionAdministration;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionAuthorization;
use System\Engine\ExtensionCapabilityRegistry;
use System\Engine\Extension\Discovery;
use System\Engine\ExtensionManager;
use System\Engine\Extension\Manifest;
use System\Library\Extension\PackageInstaller;
use System\Engine\ExtensionPackageTrust;
use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Engine\Startup;
use System\Library\ExtensionState;

final class ExtensionSetup implements Contract
{
	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$db = $registry->get('db');
		$state = new ExtensionState($db);
		$startups = new Startup();
		$capabilities = new ExtensionCapabilityRegistry();
		$capabilities->register('lightdocs.application', static function (Manifest $manifest) use ($config, $registry, $state, $startups): ExtensionApplication {
			return new ExtensionApplication(
				$manifest->name(),
				$config->all(),
				$registry->get('repository'),
				$registry->get('directives'),
				$registry->get('db'),
				$state->settings($manifest->name()),
				$startups,
			);
		});
		$manager = new ExtensionManager(
			new Discovery((string)$config->get('extension_dir')),
			$state,
			capabilities: $capabilities,
			packages: new PackageInstaller((string)$config->get('extension_dir')),
			authorizer: new ExtensionAuthorization($registry),
			trust: new ExtensionPackageTrust((string)$config->get('extension_trust_mode'), (array)$config->get('extension_trusted_signers')),
			registry: $registry,
		);
		$context = (string)$registry->get('app') === 'frontend' ? 'public' : (string)$registry->get('app');
		$runtime = $manager->boot($context);
		$extensions = new ExtensionAdministration($manager, $runtime, $state, $startups);
		$extensions->registerEvents($registry->get('event'));
		$extensions->runStartups($registry->get('event'));
		$registry->set('extensions', $extensions);
		$services = $extensions->services();
		$registry->set('git_history', $services['local_git.history'] ?? null);
		$registry->set('git_preflight', $services['local_git.preflight'] ?? null);
		$config->set('admin_navigation', $extensions->navigationItems());
		$config->set('extension_assets', $extensions->assets());
		$registry->set('extension_services', $services);
	}
}

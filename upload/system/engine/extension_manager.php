<?php

declare(strict_types=1);

namespace System\Engine;

use LogicException;
use RuntimeException;
use System\Library\ExtensionPackageInstaller;
use System\Library\ExtensionRecoveryReport;
use System\Library\ExtensionState;
use System\Library\PreparedExtension;

final class ExtensionManager
{
	private ExtensionDiscovery $discovery;
	private ExtensionState $installations;
	private ExtensionFactory $factory;
	private ExtensionCapabilityRegistry $capabilities;
	private ExtensionCompatibility $compatibility;
	private ExtensionDependencyResolver $dependencies;
	private ?ExtensionAuthorization $authorizer;
	private ExtensionPackageTrust $trust;
	private ?ExtensionPackageInstaller $packages;
	private ?Autoloader $autoloader;

	/** @var array<string,string> */
	private array $platformVersions;

	private bool $booted = false;
	private ?ExtensionRuntime $runtime = null;

	/** @var array<string,ExtensionManifest> */
	private array $catalog = [];

	/** @param array<string,string> $platformVersions */
	public function __construct(
		ExtensionDiscovery $discovery,
		ExtensionState $installations,
		?ExtensionFactory $factory = null,
		?ExtensionCapabilityRegistry $capabilities = null,
		array $platformVersions = [],
		?Autoloader $autoloader = null,
		?ExtensionCompatibility $compatibility = null,
		?ExtensionPackageInstaller $packages = null,
		?ExtensionDependencyResolver $dependencies = null,
		?ExtensionAuthorization $authorizer = null,
		?ExtensionPackageTrust $trust = null
	) {
		$this->discovery = $discovery;
		$this->installations = $installations;
		$this->factory = $factory ?? new ExtensionFactory();
		$this->capabilities = $capabilities ?? new ExtensionCapabilityRegistry();
		$this->platformVersions = $platformVersions;
		$this->autoloader = $autoloader;
		$this->compatibility = $compatibility ?? new ExtensionCompatibility();
		$this->packages = $packages;
		$this->dependencies = $dependencies ?? new ExtensionDependencyResolver();
		$this->authorizer = $authorizer;
		$this->trust = $trust ?? new ExtensionPackageTrust();
	}

	public function boot(string $context): ExtensionRuntime
	{
		if ($this->booted) {
			throw new LogicException('ExtensionManager can only boot once.');
		}
		$this->booted = true;

		$discovered = $this->discovery->discover();
		$this->catalog = $discovered;
		foreach ($discovered as $name => $manifest) {
			$installation = $this->installations->find($name);
			if ($installation === null) {
				$receipt = $this->packages?->receipt($name);
				$installation = $receipt === null
					? ExtensionInstallation::bundled($manifest)
					: new ExtensionInstallation($name, $receipt->version(), 'uploaded', ExtensionInstallation::INSTALLED_DISABLED, false, $receipt->packageHash(), $receipt->installedAt(), time());
				$this->installations->save($installation);
			} elseif ($installation->source() === 'bundled' && $installation->version() !== $manifest->version()) {
				$installation = $installation->withVersion($manifest->version());
				$this->installations->save($installation);
			}

			if ($installation->source() === 'uploaded' && ($this->packages === null || !$this->packages->verify($name))) {
				$installation = $installation->withState(ExtensionInstallation::BROKEN, false, 'Uploaded extension receipt or files failed verification.');
				$this->installations->save($installation);
			}

		}

		$order = $this->dependencies->resolve($discovered, $this->installations->all(), $context);
		$extensions = [];
		$manifests = [];
		$contexts = [];
		foreach ($order as $name) {
			$manifest = $discovered[$name];
			$this->compatibility->assertCompatible($manifest, $this->platformVersions);
			foreach ($manifest->requiredCapabilities() as $capability) {
				if (!$this->capabilities->has($capability)) {
					throw new RuntimeException('Required extension capability is unavailable: ' . $capability . ' (' . $name . ')');
				}
			}

			$this->registerNamespaces($manifest);
			$resolvedCapabilities = [];
			foreach (array_merge($manifest->requiredCapabilities(), $manifest->capabilities()['optional']) as $capability) {
				if ($this->capabilities->has($capability)) {
					$resolvedCapabilities[$capability] = $this->capabilities->resolve($capability, $manifest);
				}
			}
			$extensionContext = new ExtensionContext($manifest, $resolvedCapabilities);
			$extension = $this->factory->create($manifest);
			$extension->register($extensionContext);
			$this->registerDeclarativeAssets($manifest, $extensionContext);
			$extensions[$name] = $extension;
			$manifests[$name] = $manifest;
			$contexts[$name] = $extensionContext;
		}

		$this->runtime = new ExtensionRuntime($extensions, $manifests, $contexts);

		return $this->runtime;
	}

	public function runtime(): ExtensionRuntime
	{
		if ($this->runtime === null) {
			throw new LogicException('ExtensionManager has not booted.');
		}

		return $this->runtime;
	}

	/** @return array<string,ExtensionManifest> */
	public function catalog(): array
	{
		return $this->catalog;
	}

	public function setEnabled(string $name, bool $enabled): void
	{
		$installation = $this->installations->find($name);
		if ($installation === null) {
			throw new RuntimeException('Unknown extension installation: ' . $name);
		}
		$manifest = $this->manifest($name);
		$this->authorizer?->assertAuthorized($enabled ? 'enable' : 'disable', $manifest, $installation);
		$status = $enabled ? ExtensionInstallation::ENABLED : ($installation->source() === 'uploaded' ? ExtensionInstallation::INSTALLED_DISABLED : ExtensionInstallation::DISCOVERED);
		$updated = $installation->withState($status, $enabled);
		$installations = $this->installations->all();
		$installations[$name] = $updated;
		$this->dependencies->assertValidState($this->manifests(), $installations);
		$this->installations->save($updated);
	}

	public function installArchive(string $archivePath, ?ExtensionPackageProof $proof = null, ?ExtensionCatalogEntry $catalogEntry = null): ExtensionInstallation
	{
		$packages = $this->requirePackages();
		$prepared = $packages->prepare($archivePath);
		try {
			$this->assertCatalogCandidate($prepared, $catalogEntry);
			$current = $this->installations->find($prepared->manifest()->name());
			if ($current !== null) {
				if ($current->source() !== 'uploaded') {
					throw new RuntimeException('Bundled extensions cannot be replaced: ' . $prepared->manifest()->name());
				}
				$prepared->cleanup();

				return $this->upgradeArchive($current->name(), $archivePath, $proof, $catalogEntry);
			}
			$this->trust->assertTrusted($prepared->archiveSha256(), $proof);
			$this->compatibility->assertCompatible($prepared->manifest(), $this->platformVersions);
			foreach ($prepared->manifest()->requiredCapabilities() as $capability) {
				if (!$this->capabilities->has($capability)) {
					throw new RuntimeException('Required extension capability is unavailable: ' . $capability);
				}
			}
			$this->authorizer?->assertAuthorized('install', $prepared->manifest());
			$receipt = $packages->install($prepared);
			$installation = new ExtensionInstallation(
				$receipt->name(),
				$receipt->version(),
				'uploaded',
				ExtensionInstallation::INSTALLED_DISABLED,
				false,
				$receipt->packageHash(),
				$receipt->installedAt(),
				$receipt->installedAt()
			);
			$this->installations->save($installation);

			return $installation;
		} finally {
			$prepared->cleanup();
		}
	}

	public function installCatalogArchive(string $archivePath, ExtensionCatalogEntry $entry): ExtensionInstallation
	{
		return $this->installArchive($archivePath, $entry->proof(), $entry);
	}

	public function upgradeArchive(string $name, string $archivePath, ?ExtensionPackageProof $proof = null, ?ExtensionCatalogEntry $catalogEntry = null): ExtensionInstallation
	{
		$current = $this->installations->find($name);
		if ($current === null || $current->source() !== 'uploaded') {
			throw new RuntimeException('Only uploaded extensions can be upgraded: ' . $name);
		}
		$packages = $this->requirePackages();
		$prepared = $packages->prepare($archivePath);
		try {
			$this->assertCatalogCandidate($prepared, $catalogEntry);
			$this->trust->assertTrusted($prepared->archiveSha256(), $proof);
			$this->compatibility->assertCompatible($prepared->manifest(), $this->platformVersions);
			$this->compatibility->assertUpgrade($current->version(), $prepared->manifest());
			foreach ($prepared->manifest()->requiredCapabilities() as $capability) {
				if (!$this->capabilities->has($capability)) {
					throw new RuntimeException('Required extension capability is unavailable: ' . $capability);
				}
			}
			$this->authorizer?->assertAuthorized('upgrade', $prepared->manifest(), $current);
			if ($current->enabled()) {
				$manifests = $this->manifests();
				$manifests[$name] = $prepared->manifest();
				$this->dependencies->assertValidState($manifests, $this->installations->all());
			}
			$this->installations->save($current->withState(ExtensionInstallation::UPGRADING, $current->enabled()));
			try {
				$receipt = $packages->upgrade($name, $prepared);
				$status = $current->enabled() ? ExtensionInstallation::ENABLED : ExtensionInstallation::INSTALLED_DISABLED;
				$updated = $current->withVersion($receipt->version(), $receipt->packageHash())->withState($status, $current->enabled());
				$this->installations->save($updated);

				return $updated;
			} catch (\Throwable $exception) {
				$status = $current->enabled() ? ExtensionInstallation::ENABLED : ExtensionInstallation::INSTALLED_DISABLED;
				$this->installations->save($current->withState($status, $current->enabled(), $exception->getMessage()));
				throw $exception;
			}
		} finally {
			$prepared->cleanup();
		}
	}

	public function uninstall(string $name): void
	{
		$current = $this->installations->find($name);
		if ($current === null || $current->source() !== 'uploaded') {
			throw new RuntimeException('Only uploaded extensions can be uninstalled: ' . $name);
		}
		$this->authorizer?->assertAuthorized('uninstall', $this->manifest($name), $current);
		$manifests = $this->manifests();
		$installations = $this->installations->all();
		unset($manifests[$name], $installations[$name]);
		$this->dependencies->assertValidState($manifests, $installations);
		$this->installations->save($current->withState(ExtensionInstallation::REMOVING, false));
		try {
			$this->requirePackages()->remove($name);
			$this->installations->remove($name);
		} catch (\Throwable $exception) {
			$this->installations->save($current->withState(ExtensionInstallation::BROKEN, false, $exception->getMessage()));
			throw $exception;
		}
	}

	public function recover(): ExtensionRecoveryReport
	{
		$packages = $this->requirePackages();
		$report = $packages->recover();
		foreach ($this->installations->all() as $name => $installation) {
			if ($installation->source() !== 'uploaded' || !in_array($installation->status(), [ExtensionInstallation::UPGRADING, ExtensionInstallation::REMOVING], true)) {
				continue;
			}
			$receipt = $packages->receipt($name);
			if ($receipt === null) {
				if ($installation->status() === ExtensionInstallation::REMOVING) {
					$this->installations->remove($name);
				} else {
					$this->installations->save($installation->withState(ExtensionInstallation::BROKEN, false, 'Extension recovery could not restore a valid receipt.'));
				}
				continue;
			}
			$status = $installation->enabled() ? ExtensionInstallation::ENABLED : ExtensionInstallation::INSTALLED_DISABLED;
			$recovered = new ExtensionInstallation($name, $receipt->version(), 'uploaded', $status, $installation->enabled(), $receipt->packageHash(), $receipt->installedAt(), time());
			$this->installations->save($recovered);
		}

		return $report;
	}

	/** @return array<string,ExtensionInstallation> */
	public function installations(): array
	{
		return $this->installations->all();
	}

	private function registerNamespaces(ExtensionManifest $manifest): void
	{
		if ($this->autoloader === null) {
			return;
		}
		$root = dirname($manifest->source()) . DIRECTORY_SEPARATOR;
		foreach (($manifest->resources()['namespaces'] ?? []) as $namespace => $directory) {
			$this->autoloader->register((string) $namespace, $root . rtrim((string) $directory, '/\\') . DIRECTORY_SEPARATOR);
		}
	}

	private function registerDeclarativeAssets(ExtensionManifest $manifest, ExtensionContext $context): void
	{
		foreach (($manifest->resources()['assets'] ?? []) as $scope => $assets) {
			foreach (($assets['styles'] ?? []) as $path) {
				$context->asset((string) $scope, 'style', (string) $path);
			}
			foreach (($assets['scripts'] ?? []) as $path) {
				$context->asset((string) $scope, 'script', (string) $path);
			}
		}
	}

	private function requirePackages(): ExtensionPackageInstaller
	{
		if ($this->packages === null) {
			throw new LogicException('Extension package installation is not configured.');
		}

		return $this->packages;
	}

	private function manifest(string $name): ExtensionManifest
	{
		$manifests = $this->manifests();
		if (!isset($manifests[$name])) throw new RuntimeException('Unknown extension manifest: ' . $name);

		return $manifests[$name];
	}

	/** @return array<string,ExtensionManifest> */
	private function manifests(): array
	{
		return $this->catalog !== [] ? $this->catalog : $this->discovery->discover();
	}

	private function assertCatalogCandidate(PreparedExtension $prepared, ?ExtensionCatalogEntry $entry): void
	{
		if ($entry === null) return;
		if ($entry->name() !== $prepared->manifest()->name() || $entry->version() !== $prepared->manifest()->version()) {
			throw new RuntimeException('Extension catalog metadata does not match the prepared package manifest.');
		}
		if (!hash_equals($entry->archiveSha256(), $prepared->archiveSha256())) {
			throw new RuntimeException('Extension catalog archive hash verification failed.');
		}
	}
}

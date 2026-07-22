<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use LogicException;
use RuntimeException;
use System\Engine\Autoloader;
use System\Engine\Extension as ExtensionRuntime;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;
use System\Engine\Extension\Discovery;
use System\Engine\Extension\Manifest;
use System\Engine\Extension\RuntimeBuilder;
use System\Library\Extension\PackageInstaller;
use System\Library\Extension\RecoveryReport;
use System\Library\ExtensionState;
use System\Library\Extension\Prepared;

final class Manager
{
	private Discovery $discovery;
	private ExtensionState $installations;
	private Factory $factory;
	private CapabilityRegistry $capabilities;
	private Compatibility $compatibility;
	private DependencyResolver $dependencies;
	private ?Authorization $authorizer;
	private PackageTrust $trust;
	private ?PackageInstaller $packages;
	private ?Autoloader $autoloader;
	private ?RuntimeBuilder $runtimeBuilder;

	/** @var array<string,string> */
	private array $platformVersions;

	private bool $booted = false;
	private ?ExtensionRuntime $runtime = null;

	/** @var array<string,Manifest> */
	private array $catalog = [];

	/** @param array<string,string> $platformVersions */
	public function __construct(
		Discovery $discovery,
		ExtensionState $installations,
		?Factory $factory = null,
		?CapabilityRegistry $capabilities = null,
		array $platformVersions = [],
		?Autoloader $autoloader = null,
		?Compatibility $compatibility = null,
		?PackageInstaller $packages = null,
		?DependencyResolver $dependencies = null,
		?Authorization $authorizer = null,
		?PackageTrust $trust = null,
		?RuntimeBuilder $runtimeBuilder = null
	) {
		$this->discovery = $discovery;
		$this->installations = $installations;
		$this->factory = $factory ?? new Factory();
		$this->capabilities = $capabilities ?? new CapabilityRegistry();
		$this->platformVersions = $platformVersions;
		$this->autoloader = $autoloader;
		$this->compatibility = $compatibility ?? new Compatibility();
		$this->packages = $packages;
		$this->dependencies = $dependencies ?? new DependencyResolver();
		$this->authorizer = $authorizer;
		$this->trust = $trust ?? new PackageTrust();
		$this->runtimeBuilder = $runtimeBuilder;
	}

	public function boot(string $context): ExtensionRuntime
	{
		if ($this->booted) {
			throw new LogicException('Manager can only boot once.');
		}
		$this->booted = true;

		$discovered = $this->discovery->discover();
		$this->catalog = $discovered;
		foreach ($discovered as $name => $manifest) {
			$installation = $this->installations->find($name);
			if ($installation === null) {
				$receipt = $this->packages?->receipt($name);
				$installation = $receipt === null
					? Installation::bundled($manifest)
					: new Installation($name, $receipt->version(), 'uploaded', Installation::INSTALLED_DISABLED, false, $receipt->packageHash(), $receipt->installedAt(), time());
				$this->installations->save($installation);
			} elseif ($installation->source() === 'bundled' && $installation->version() !== $manifest->version()) {
				$installation = $installation->withVersion($manifest->version());
				$this->installations->save($installation);
			}

			if ($installation->source() === 'uploaded' && ($this->packages === null || !$this->packages->verify($name))) {
				$installation = $installation->withState(Installation::BROKEN, false, 'Uploaded extension receipt or files failed verification.');
				$this->installations->save($installation);
			}

		}

		if ($this->runtimeBuilder !== null) {
			$installations = $this->installations;
			$capabilities = $this->capabilities;
			$factory = $this->factory;
			$this->runtime = $this->runtimeBuilder->build(
				$discovered,
				$context,
				static fn (Manifest $manifest): bool => $installations->find($manifest->name())?->enabled() ?? false,
				static fn (Manifest $manifest): Contract => $factory->create($manifest),
				static fn (string $name, Manifest $manifest): ?object => $capabilities->has($name) ? $capabilities->resolve($name, $manifest) : null,
				$this->platformVersions
			);

			return $this->runtime;
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
			$extensionContext = new Context($manifest, $resolvedCapabilities);
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
			throw new LogicException('Manager has not booted.');
		}

		return $this->runtime;
	}

	/** @return array<string,Manifest> */
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
		$status = $enabled ? Installation::ENABLED : ($installation->source() === 'uploaded' ? Installation::INSTALLED_DISABLED : Installation::DISCOVERED);
		$updated = $installation->withState($status, $enabled);
		$installations = $this->installations->all();
		$installations[$name] = $updated;
		$this->dependencies->assertValidState($this->manifests(), $installations);
		$this->installations->save($updated);
	}

	public function installArchive(string $archivePath, ?PackageProof $proof = null, ?CatalogEntry $catalogEntry = null): Installation
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
			$installation = new Installation(
				$receipt->name(),
				$receipt->version(),
				'uploaded',
				Installation::INSTALLED_DISABLED,
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

	public function installCatalogArchive(string $archivePath, CatalogEntry $entry): Installation
	{
		return $this->installArchive($archivePath, $entry->proof(), $entry);
	}

	public function upgradeArchive(string $name, string $archivePath, ?PackageProof $proof = null, ?CatalogEntry $catalogEntry = null): Installation
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
			$this->installations->save($current->withState(Installation::UPGRADING, $current->enabled()));
			try {
				$receipt = $packages->upgrade($name, $prepared);
				$status = $current->enabled() ? Installation::ENABLED : Installation::INSTALLED_DISABLED;
				$updated = $current->withVersion($receipt->version(), $receipt->packageHash())->withState($status, $current->enabled());
				$this->installations->save($updated);

				return $updated;
			} catch (\Throwable $exception) {
				$status = $current->enabled() ? Installation::ENABLED : Installation::INSTALLED_DISABLED;
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
		$this->installations->save($current->withState(Installation::REMOVING, false));
		try {
			$this->requirePackages()->remove($name);
			$this->installations->remove($name);
		} catch (\Throwable $exception) {
			$this->installations->save($current->withState(Installation::BROKEN, false, $exception->getMessage()));
			throw $exception;
		}
	}

	public function recover(): RecoveryReport
	{
		$packages = $this->requirePackages();
		$report = $packages->recover();
		foreach ($this->installations->all() as $name => $installation) {
			if ($installation->source() !== 'uploaded' || !in_array($installation->status(), [Installation::UPGRADING, Installation::REMOVING], true)) {
				continue;
			}
			$receipt = $packages->receipt($name);
			if ($receipt === null) {
				if ($installation->status() === Installation::REMOVING) {
					$this->installations->remove($name);
				} else {
					$this->installations->save($installation->withState(Installation::BROKEN, false, 'Extension recovery could not restore a valid receipt.'));
				}
				continue;
			}
			$status = $installation->enabled() ? Installation::ENABLED : Installation::INSTALLED_DISABLED;
			$recovered = new Installation($name, $receipt->version(), 'uploaded', $status, $installation->enabled(), $receipt->packageHash(), $receipt->installedAt(), time());
			$this->installations->save($recovered);
		}

		return $report;
	}

	/** @return array<string,Installation> */
	public function installations(): array
	{
		return $this->installations->all();
	}

	private function registerNamespaces(Manifest $manifest): void
	{
		if ($this->autoloader === null) {
			return;
		}
		$root = dirname($manifest->source()) . DIRECTORY_SEPARATOR;
		foreach (($manifest->resources()['namespaces'] ?? []) as $namespace => $directory) {
			$this->autoloader->register((string) $namespace, $root . rtrim((string) $directory, '/\\') . DIRECTORY_SEPARATOR);
		}
	}

	private function registerDeclarativeAssets(Manifest $manifest, Context $context): void
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

	private function requirePackages(): PackageInstaller
	{
		if ($this->packages === null) {
			throw new LogicException('Extension package installation is not configured.');
		}

		return $this->packages;
	}

	private function manifest(string $name): Manifest
	{
		$manifests = $this->manifests();
		if (!isset($manifests[$name])) throw new RuntimeException('Unknown extension manifest: ' . $name);

		return $manifests[$name];
	}

	/** @return array<string,Manifest> */
	private function manifests(): array
	{
		return $this->catalog !== [] ? $this->catalog : $this->discovery->discover();
	}

	private function assertCatalogCandidate(Prepared $prepared, ?CatalogEntry $entry): void
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

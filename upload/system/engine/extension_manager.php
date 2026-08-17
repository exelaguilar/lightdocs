<?php

declare(strict_types=1);

namespace System\Engine;

use Composer\Semver\Comparator;
use LogicException;
use RuntimeException;
use System\Engine\Extension\Discovery;
use System\Engine\Extension\Manifest;
use System\Engine\Extension\ResourceMountApplier;
use System\Engine\Extension\RuntimeBuilder;
use System\Engine\Extension;
use System\Library\Extension\PackageInstaller;
use System\Library\ExtensionState;
use System\Library\Extension\Prepared;

final class ExtensionManager
{
	private Discovery $discovery;
	private ExtensionState $installations;
	private ?ExtensionAuthorization $authorizer;
	private ExtensionPackageTrust $trust;
	private ?PackageInstaller $packages;
	private ?Registry $registry;

	private bool $booted = false;
	private ?Extension $runtime = null;

	/** @var array<string,Manifest> */
	private array $catalog = [];

	public function __construct(
		Discovery $discovery,
		ExtensionState $installations,
		?PackageInstaller $packages = null,
		?ExtensionAuthorization $authorizer = null,
		?ExtensionPackageTrust $trust = null,
		?Registry $registry = null
	) {
		$this->discovery = $discovery;
		$this->installations = $installations;
		$this->packages = $packages;
		$this->authorizer = $authorizer;
		$this->trust = $trust ?? new ExtensionPackageTrust();
		$this->registry = $registry;
	}

	public function boot(string $context): Extension
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

		$registry = $this->registry ?? throw new LogicException('ExtensionManager requires a registry.');
		$runtime_builder = new RuntimeBuilder($registry, new ResourceMountApplier($registry));
		$this->runtime = $runtime_builder->build(
			$discovered,
			$context,
			array_map(
				fn (Manifest $manifest): bool => $this->installations->find($manifest->name())?->enabled() ?? false,
				$discovered,
			),
			array_map(
				fn (Manifest $manifest): array => $this->installations->settings($manifest->name()),
				$discovered,
			),
		);

		return $this->runtime;
	}

	public function runtime(): Extension
	{
		if ($this->runtime === null) {
			throw new LogicException('ExtensionManager has not booted.');
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
		$status = $enabled ? ExtensionInstallation::ENABLED : ($installation->source() === 'uploaded' ? ExtensionInstallation::INSTALLED_DISABLED : ExtensionInstallation::DISCOVERED);
		$updated = $installation->withState($status, $enabled);
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
			$this->assertUpgrade($current->version(), $prepared->manifest());
			$this->authorizer?->assertAuthorized('upgrade', $prepared->manifest(), $current);
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
		$this->installations->save($current->withState(ExtensionInstallation::REMOVING, false));
		try {
			$this->requirePackages()->remove($name);
			$this->installations->remove($name);
		} catch (\Throwable $exception) {
			$this->installations->save($current->withState(ExtensionInstallation::BROKEN, false, $exception->getMessage()));
			throw $exception;
		}
	}

	/** @return array<string,ExtensionInstallation> */
	public function installations(): array
	{
		return $this->installations->all();
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

	private function assertCatalogCandidate(Prepared $prepared, ?ExtensionCatalogEntry $entry): void
	{
		if ($entry === null) return;
		if ($entry->name() !== $prepared->manifest()->name() || $entry->version() !== $prepared->manifest()->version()) {
			throw new RuntimeException('Extension catalog metadata does not match the prepared package manifest.');
		}
		if (!hash_equals($entry->archiveSha256(), $prepared->archiveSha256())) {
			throw new RuntimeException('Extension catalog archive hash verification failed.');
		}
	}

	private function assertUpgrade(string $currentVersion, Manifest $candidate): void
	{
		try {
			$newer = Comparator::greaterThan($candidate->version(), $currentVersion);
		} catch (\UnexpectedValueException $exception) {
			throw new RuntimeException('Extension upgrade versions are invalid.', 0, $exception);
		}
		if (!$newer) {
			throw new RuntimeException(sprintf('Extension upgrade must increase the version from %s; received %s.', $currentVersion, $candidate->version()));
		}
	}
}

<?php

declare(strict_types=1);

namespace System\Engine;

use InvalidArgumentException;

final class ExtensionInstallation
{
	public const DISCOVERED = 'discovered';
	public const INSTALLED_DISABLED = 'installed_disabled';
	public const ENABLING = 'enabling';
	public const ENABLED = 'enabled';
	public const DISABLING = 'disabling';
	public const UPGRADING = 'upgrading';
	public const REMOVING = 'removing';
	public const BROKEN = 'broken';

	private string $name;
	private string $version;
	private string $source;
	private string $status;
	private bool $enabled;
	private string $packageHash;
	private int $installedAt;
	private int $updatedAt;
	private ?string $error;

	public function __construct(
		string $name,
		string $version,
		string $source,
		string $status,
		bool $enabled,
		string $packageHash = '',
		int $installedAt = 0,
		int $updatedAt = 0,
		?string $error = null
	) {
		if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
			throw new InvalidArgumentException('Invalid extension installation name.');
		}
		if (!in_array($source, ['bundled', 'uploaded'], true)) {
			throw new InvalidArgumentException('Invalid extension installation source.');
		}
		if (!in_array($status, self::statuses(), true)) {
			throw new InvalidArgumentException('Invalid extension installation status.');
		}
		$this->name = $name;
		$this->version = $version;
		$this->source = $source;
		$this->status = $status;
		$this->enabled = $enabled;
		$this->packageHash = $packageHash;
		$this->installedAt = $installedAt;
		$this->updatedAt = $updatedAt;
		$this->error = $error;
	}

	public static function bundled(ExtensionManifest $manifest, ?int $now = null): self
	{
		$time = $now ?? time();
		$enabled = $manifest->defaultEnabled();

		return new self($manifest->name(), $manifest->version(), 'bundled', $enabled ? self::ENABLED : self::DISCOVERED, $enabled, '', $time, $time);
	}

	public function withState(string $status, bool $enabled, ?string $error = null, ?int $now = null): self
	{
		return new self($this->name, $this->version, $this->source, $status, $enabled, $this->packageHash, $this->installedAt, $now ?? time(), $error);
	}

	public function withVersion(string $version, string $packageHash = '', ?int $now = null): self
	{
		return new self($this->name, $version, $this->source, $this->status, $this->enabled, $packageHash !== '' ? $packageHash : $this->packageHash, $this->installedAt, $now ?? time(), $this->error);
	}

	public function name(): string { return $this->name; }
	public function version(): string { return $this->version; }
	public function source(): string { return $this->source; }
	public function status(): string { return $this->status; }
	public function enabled(): bool { return $this->enabled; }
	public function packageHash(): string { return $this->packageHash; }
	public function installedAt(): int { return $this->installedAt; }
	public function updatedAt(): int { return $this->updatedAt; }
	public function error(): ?string { return $this->error; }

	/** @return list<string> */
	public static function statuses(): array
	{
		return [self::DISCOVERED, self::INSTALLED_DISABLED, self::ENABLING, self::ENABLED, self::DISABLING, self::UPGRADING, self::REMOVING, self::BROKEN];
	}
}

<?php

declare(strict_types=1);

namespace System\Engine;

use InvalidArgumentException;

final class ExtensionCatalogEntry
{
	private string $name;
	private string $version;
	private string $channel;
	private string $archiveUri;
	private string $archiveSha256;
	private ?ExtensionPackageProof $proof;

	public function __construct(string $name, string $version, string $channel, string $archiveUri, string $archiveSha256, ?ExtensionPackageProof $proof = null)
	{
		if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) throw new InvalidArgumentException('Invalid extension catalog name.');
		if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$/', $version)) throw new InvalidArgumentException('Invalid extension catalog version.');
		if (!preg_match('/^[a-z][a-z0-9_-]*$/', $channel)) throw new InvalidArgumentException('Invalid extension catalog channel.');
		$parts = parse_url($archiveUri);
		if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) throw new InvalidArgumentException('Extension catalog archive URI must use HTTPS.');
		if (!preg_match('/^[a-f0-9]{64}$/', $archiveSha256)) throw new InvalidArgumentException('Invalid extension catalog archive hash.');
		$this->name = $name;
		$this->version = $version;
		$this->channel = $channel;
		$this->archiveUri = $archiveUri;
		$this->archiveSha256 = $archiveSha256;
		$this->proof = $proof;
	}

	public function name(): string { return $this->name; }
	public function version(): string { return $this->version; }
	public function channel(): string { return $this->channel; }
	public function archiveUri(): string { return $this->archiveUri; }
	public function archiveSha256(): string { return $this->archiveSha256; }
	public function proof(): ?ExtensionPackageProof { return $this->proof; }
}

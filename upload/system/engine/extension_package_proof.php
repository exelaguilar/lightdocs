<?php

declare(strict_types=1);

namespace System\Engine;

use InvalidArgumentException;

final class ExtensionPackageProof
{
	private string $signer;
	private string $algorithm;
	private string $signature;

	public function __construct(string $signer, string $algorithm, string $signature)
	{
		if (!preg_match('/^[a-z][a-z0-9_.-]*$/', $signer)) throw new InvalidArgumentException('Invalid extension package signer.');
		if ($algorithm !== 'openssl-sha256') throw new InvalidArgumentException('Unsupported extension package signature algorithm.');
		if (base64_decode($signature, true) === false) throw new InvalidArgumentException('Invalid extension package signature encoding.');
		$this->signer = $signer;
		$this->algorithm = $algorithm;
		$this->signature = $signature;
	}

	public function signer(): string { return $this->signer; }
	public function algorithm(): string { return $this->algorithm; }
	public function signature(): string { return $this->signature; }

	/** @return array{signer:string,algorithm:string,signature:string} */
	public function toArray(): array { return ['signer' => $this->signer, 'algorithm' => $this->algorithm, 'signature' => $this->signature]; }

	/** @param array<string,mixed> $data */
	public static function fromArray(array $data): self
	{
		return new self((string) ($data['signer'] ?? ''), (string) ($data['algorithm'] ?? ''), (string) ($data['signature'] ?? ''));
	}
}

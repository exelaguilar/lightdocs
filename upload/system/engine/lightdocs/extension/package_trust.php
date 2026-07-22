<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use InvalidArgumentException;
use RuntimeException;
use System\Library\Extension\SignatureVerifier;

final class PackageTrust
{
	public const ALLOW_UNSIGNED = 'allow_unsigned';
	public const REQUIRE_SIGNATURE = 'require_signature';

	private string $mode;
	/** @var array<string,string> */
	private array $publicKeys;
	/** @var callable(string,string,string,string):bool */
	private $verifier;

	/** @param array<string,string> $publicKeys */
	public function __construct(string $mode = self::ALLOW_UNSIGNED, array $publicKeys = [], ?callable $verifier = null)
	{
		if (!in_array($mode, [self::ALLOW_UNSIGNED, self::REQUIRE_SIGNATURE], true)) throw new InvalidArgumentException('Invalid extension package trust mode.');
		foreach ($publicKeys as $signer => $key) {
			if (!preg_match('/^[a-z][a-z0-9_.-]*$/', (string) $signer) || !is_string($key) || trim($key) === '') throw new InvalidArgumentException('Invalid trusted extension signer configuration.');
		}
		$this->mode = $mode;
		$this->publicKeys = $publicKeys;
		$this->verifier = $verifier ?? static fn (string $message, string $signature, string $publicKey, string $algorithm): bool => (new SignatureVerifier())->verify($message, $signature, $publicKey, $algorithm);
	}

	public function assertTrusted(string $archiveSha256, ?PackageProof $proof): void
	{
		if (!preg_match('/^[a-f0-9]{64}$/', $archiveSha256)) throw new RuntimeException('Invalid extension archive hash.');
		if ($proof === null) {
			if ($this->mode === self::REQUIRE_SIGNATURE) throw new RuntimeException('A trusted extension package signature is required.');
			return;
		}
		$key = $this->publicKeys[$proof->signer()] ?? null;
		if ($key === null) throw new RuntimeException('Extension package signer is not trusted: ' . $proof->signer());
		if (!(($this->verifier)($archiveSha256, $proof->signature(), $key, $proof->algorithm()))) {
			throw new RuntimeException('Extension package signature verification failed.');
		}
	}
}

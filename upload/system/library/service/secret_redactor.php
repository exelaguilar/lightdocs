<?php

declare(strict_types=1);

namespace System\Library\Service;

/**
 * Conservative, format-aware secret redaction for exported copies.
 *
 * Canonical source files must never be passed back to the editor after this
 * transformation. The redactor is deliberately shared by exports and Git
 * mirrors so both paths apply the same safety policy.
 */
final class SecretRedactor
{
	public const REPLACEMENT = '<redacted>';

	/** @return array{contents:string,replacements:int,categories:array<string,int>} */
	public function redact(string $contents): array
	{
		$categories = [];
		$replace = static function (string $category, string $replacement) use (&$categories): string {
			$categories[$category] = ($categories[$category] ?? 0) + 1;
			return $replacement;
		};

		$contents = preg_replace_callback(
			'/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----.*?-----END (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/s',
			static fn (array $match): string => $replace('private-key', '-----BEGIN PRIVATE KEY-----\n' . self::REPLACEMENT . '\n-----END PRIVATE KEY-----'),
			$contents
		) ?? $contents;

		$secret_key = '(?:[A-Z0-9_]*(?:SECRET|TOKEN|PASSWORD|PASSWD|PASSPHRASE|API_KEY|PRIVATE_KEY|CLIENT_SECRET|ACCESS_KEY|CREDENTIAL)[A-Z0-9_]*|secret|token|password|passwd|passphrase|api[_-]?key|private[_-]?key|client[_-]?secret|access[_-]?key|credential)';
		$contents = preg_replace_callback(
			'/^([ \t]*(?!contains_secrets\b)' . $secret_key . '[ \t]*[:=][ \t]*)(?![|>][-+]?[ \t]*(?=\r?$))([^\r\n#][^\r\n]*?)(?=\r?$)/im',
			static fn (array $match): string => $replace('assignment', $match[1] . '"' . self::REPLACEMENT . '"'),
			$contents
		) ?? $contents;

		$contents = preg_replace_callback(
			'/(--(?:secret|token|password|passwd|passphrase|api-key|private-key|client-secret|access-key)\s+)(?:"[^"]*"|\'[^\']*\'|\S+)/i',
			static fn (array $match): string => $replace('command-argument', $match[1] . '"' . self::REPLACEMENT . '"'),
			$contents
		) ?? $contents;

		$contents = preg_replace_callback(
			'/\b(?:github_pat_[A-Za-z0-9_]{20,}|gh[opurs]_[A-Za-z0-9]{20,}|glpat-[A-Za-z0-9_-]{20,})\b/',
			static fn (array $match): string => $replace('provider-token', self::REPLACEMENT),
			$contents
		) ?? $contents;

		ksort($categories);
		return ['contents' => $contents, 'replacements' => array_sum($categories), 'categories' => $categories];
	}
}

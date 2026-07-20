<?php

declare(strict_types=1);

namespace Extension\Mail;

use RuntimeException;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\MailProvider;

final class Extension implements ExtensionInterface, MailProvider
{
	private ExtensionApplication $context;

	public function register(ExtensionContext $context): void
	{
		$this->context = $this->application($context);
		$context->services()->set('mail.provider', $this);
	}

	private function application(ExtensionContext $context): ExtensionApplication
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof ExtensionApplication) throw new RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}

	/** @throws RuntimeException if the connection, authentication, or delivery fails. */
	public function send(string $recipient, string $subject, string $body): void
	{
		$host = trim((string) ($this->context->settings['host'] ?? ''));
		$from = trim((string) ($this->context->settings['from_email'] ?? ''));
		if ($host === '' || $from === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
			throw new RuntimeException('Mail is not configured with a host and a valid From address.');
		}

		$port = max(1, min(65535, (int) ($this->context->settings['port'] ?? 587)));
		$encryption = (string) ($this->context->settings['encryption'] ?? 'tls');
		$timeout = max(1, min(60, (int) ($this->context->settings['timeout'] ?? 10)));

		$transport = $encryption === 'ssl' ? 'ssl://' . $host : $host;
		$socket = @stream_socket_client($transport . ':' . $port, $error_code, $error_message, $timeout);
		if ($socket === false) {
			throw new RuntimeException('Could not connect to the SMTP server: ' . $error_message);
		}
		stream_set_timeout($socket, $timeout);

		try {
			$this->expect($socket, 220);
			$this->command($socket, 'EHLO ' . (parse_url($from, PHP_URL_HOST) ?: 'localhost'), 250);

			if ($encryption === 'tls') {
				$this->command($socket, 'STARTTLS', 220);
				if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
					throw new RuntimeException('The SMTP server did not accept a TLS upgrade.');
				}
				$this->command($socket, 'EHLO ' . (parse_url($from, PHP_URL_HOST) ?: 'localhost'), 250);
			}

			$username = trim((string) ($this->context->settings['username'] ?? ''));
			$password = (string) ($this->context->settings['password'] ?? '');
			if ($username !== '') {
				$this->command($socket, 'AUTH LOGIN', 334);
				$this->command($socket, base64_encode($username), 334);
				$this->command($socket, base64_encode($password), 235);
			}

			$this->command($socket, 'MAIL FROM:<' . $from . '>', 250);
			$this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
			$this->command($socket, 'DATA', 354);

			$from_name = trim((string) ($this->context->settings['from_name'] ?? ''));
			$headers = [
				'From: ' . ($from_name !== '' ? '"' . str_replace(['"', "\r", "\n"], '', $from_name) . '" ' : '') . '<' . $from . '>',
				'To: <' . $recipient . '>',
				'Subject: ' . $this->encodeHeader($subject),
				'MIME-Version: 1.0',
				'Content-Type: text/html; charset=UTF-8',
				'Content-Transfer-Encoding: 8bit',
				'Date: ' . date('r'),
				'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . (parse_url($from, PHP_URL_HOST) ?: 'localhost') . '>',
			];
			// Dot-stuff any line that starts with a lone "." so it is not read as the end-of-data marker.
			$escaped_body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body)) ?? $body;
			$this->write($socket, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $escaped_body) . "\r\n.");
			$this->expect($socket, 250);

			$this->write($socket, 'QUIT');
		} finally {
			fclose($socket);
		}
	}

	/** @param resource $socket */
	private function command($socket, string $line, int|array $expected): void
	{
		$this->write($socket, $line);
		$this->expect($socket, $expected);
	}

	/** @param resource $socket */
	private function write($socket, string $line): void
	{
		if (@fwrite($socket, $line . "\r\n") === false) {
			throw new RuntimeException('The connection to the SMTP server was lost.');
		}
	}

	/** @param resource $socket */
	private function expect($socket, int|array $expected): void
	{
		$codes = (array) $expected;
		$last_line = '';
		do {
			$line = fgets($socket, 512);
			if ($line === false) {
				throw new RuntimeException('The SMTP server closed the connection unexpectedly.');
			}
			$last_line = $line;
		} while (isset($line[3]) && $line[3] === '-');

		$code = (int) substr($last_line, 0, 3);
		if (!in_array($code, $codes, true)) {
			throw new RuntimeException('Unexpected SMTP response: ' . trim($last_line));
		}
	}

	private function encodeHeader(string $value): string
	{
		return preg_match('/[^\x20-\x7E]/', $value) === 1
			? '=?UTF-8?B?' . base64_encode($value) . '?='
			: $value;
	}
}

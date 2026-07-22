<?php

declare(strict_types=1);

namespace Extension\Webhooks;

use PDO;
use System\Engine\Lightdocs\Extension\Application;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;
use System\Engine\WebhookProvider;
use System\Library\Http;

final class Extension implements Contract, WebhookProvider
{
	private const RETENTION_DAYS = 30;

	private PDO $db;
	private Application $context;

	public function register(Context $context): void
	{
		$this->context = $this->application($context);
		$this->db = $this->context->database->connection();
		$context->service('webhook.provider', $this);
		foreach ($this->eventNames() as $event) {
			$context->listen($event, function (mixed $payload, string $name): void {
				try {
					$this->send($name, is_array($payload) ? $payload : ['value' => $payload]);
				} catch (\Throwable) {
					// Remote delivery must never make a content change fail.
				}
			}, 'webhooks.' . str_replace('.', '_', $event));
		}
	}

	private function application(Context $context): Application
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof Application) throw new \RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}

	/** Delivers an event to every configured endpoint. One endpoint failing never blocks the others. */
	public function send(string $event, array $payload): void
	{
		foreach ($this->endpoints() as [$url, $secret]) {
			try {
				$this->deliver($url, $secret, $event, $payload);
			} catch (\Throwable) {
				// Recorded as a failed delivery below; never propagated to the caller.
			}
		}
	}

	/** @return list<array<string,mixed>> */
	public function recent(int $limit = 20): array
	{
		$statement = $this->db->prepare('SELECT endpoint, event, status_code, success, error, duration_ms, created_at FROM webhook_deliveries ORDER BY id DESC LIMIT :limit');
		$statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
		$statement->execute();
		return $statement->fetchAll();
	}

	private function deliver(string $url, string $secret, string $event, array $payload): void
	{
		$body = json_encode(['event' => $event, 'payload' => !empty($this->context->settings['include_payload']) ? $payload : [], 'sent_at' => gmdate(DATE_ATOM)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		$started = microtime(true);
		// An unreachable endpoint is an expected, already-logged outcome here (see record() below),
		// not an application error â€” suppressed so it doesn't spam the PHP error log per delivery.
		$result = (new Http())->post($url, $body, [
			'Content-Type: application/json',
			'X-Lightdocs-Event: ' . $event,
			'X-Lightdocs-Signature: sha256=' . hash_hmac('sha256', $body, $secret),
		], max(1, min(30, (int) ($this->context->settings['timeout'] ?? 5))));
		$status_code = $result['status'];
		$success = $status_code >= 200 && $status_code < 300;
		$error = $result['error'] !== '' ? 'The endpoint could not be reached.' : ($success ? '' : 'The endpoint returned status ' . $status_code . '.');
		$this->record($url, $event, $status_code, $success, $error, (int) round((microtime(true) - $started) * 1000));
	}

	private function record(string $url, string $event, int $status_code, bool $success, string $error, int $duration_ms): void
	{
		$statement = $this->db->prepare('INSERT INTO webhook_deliveries (endpoint, event, status_code, success, error, duration_ms, created_at) VALUES (:endpoint, :event, :status_code, :success, :error, :duration_ms, :created_at)');
		$statement->execute(['endpoint' => $this->redact($url), 'event' => $event, 'status_code' => $status_code, 'success' => $success ? 1 : 0, 'error' => $error, 'duration_ms' => $duration_ms, 'created_at' => time()]);
		$cleanup = $this->db->prepare('DELETE FROM webhook_deliveries WHERE created_at < :cutoff');
		$cleanup->execute(['cutoff' => time() - (self::RETENTION_DAYS * 86400)]);
	}

	/** Strips query strings so a signed URL or token-bearing endpoint never lands in the delivery log. */
	private function redact(string $url): string
	{
		$parts = parse_url($url);
		if (!is_array($parts) || empty($parts['host'])) return $url;
		return ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . ($parts['path'] ?? '');
	}

	/** @return list<array{0:string,1:string}> Validated [url, secret] pairs, one per non-empty line. */
	private function endpoints(): array
	{
		$lines = preg_split('/\r?\n/', (string) ($this->context->settings['endpoints'] ?? '')) ?: [];
		$endpoints = [];
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') continue;
			[$url, $secret] = array_pad(preg_split('/\s+/', $line, 2) ?: [], 2, '');
			if (!str_starts_with(strtolower($url), 'https://') || trim($secret) === '') continue;
			$endpoints[] = [$url, trim($secret)];
		}
		return $endpoints;
	}

	/** @return list<string> */
	private function eventNames(): array
	{
		return array_values(array_filter(array_map('trim', explode(',', (string) ($this->context->settings['events'] ?? 'content.changed')))));
	}
}

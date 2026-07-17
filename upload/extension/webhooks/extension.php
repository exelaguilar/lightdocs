<?php

declare(strict_types=1);

namespace Extension\Webhooks;

use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionManager;
use System\Engine\WebhookProvider;

final class Extension implements ExtensionInterface, WebhookProvider
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'webhooks';
	}

	public function register(ExtensionManager $extensions): void
	{
		$extensions->service('webhook.provider', $this);
		foreach ($this->eventNames() as $event) {
			$extensions->on($event, function (mixed $payload, string $name): void {
				try {
					$this->send($name, is_array($payload) ? $payload : ['value' => $payload]);
				} catch (\Throwable) {
					// Remote delivery must never make a content change fail.
				}
			}, 'webhooks.' . str_replace('.', '_', $event));
		}
	}

	public function send(string $event, array $payload): void
	{
		$url = trim((string) ($this->context->settings['endpoint_url'] ?? ''));
		$secret = (string) ($this->context->settings['secret'] ?? '');
		if ($url === '' || $secret === '' || !str_starts_with(strtolower($url), 'https://')) return;
		$body = json_encode(['event' => $event, 'payload' => !empty($this->context->settings['include_payload']) ? $payload : [], 'sent_at' => gmdate(DATE_ATOM)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		$headers = 'Content-Type: application/json' . "\r\n" . 'X-Lightdocs-Event: ' . $event . "\r\n" . 'X-Lightdocs-Signature: sha256=' . hash_hmac('sha256', $body, $secret) . "\r\n";
		$options = ['http' => ['method' => 'POST', 'header' => $headers, 'content' => $body, 'timeout' => max(1, min(30, (int) ($this->context->settings['timeout'] ?? 5))), 'ignore_errors' => true]];
		file_get_contents($url, false, stream_context_create($options));
	}

	/** @return list<string> */
	private function eventNames(): array
	{
		return array_values(array_filter(array_map('trim', explode(',', (string) ($this->context->settings['events'] ?? 'content.changed')))));
	}
}

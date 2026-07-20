<?php

declare(strict_types=1);

namespace System\Engine;

interface WebhookProvider
{
	public function send(string $event, array $payload): void;

	/** @return list<array<string,mixed>> Most recent delivery attempts, newest first. */
	public function recent(int $limit = 20): array;
}

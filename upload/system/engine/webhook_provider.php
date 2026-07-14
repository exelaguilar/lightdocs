<?php

declare(strict_types=1);

namespace System\Engine;

interface WebhookProvider
{
	public function send(string $event, array $payload): void;
}

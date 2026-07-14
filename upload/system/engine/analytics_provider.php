<?php

declare(strict_types=1);

namespace System\Engine;

interface AnalyticsProvider
{
	public function track(string $event, array $payload = []): void;
}

<?php

declare(strict_types=1);

namespace System\Engine;

interface MailProvider
{
	public function send(string $recipient, string $subject, string $body): void;
}

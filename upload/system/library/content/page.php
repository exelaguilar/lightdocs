<?php

declare(strict_types=1);

namespace System\Library\Content;

final readonly class Page
{
	public function __construct(
		public string $source_path,
		public string $relative_path,
		public string $url,
		public string $title,
		public string $description,
		public string $markdown,
		public array $meta,
		public int $modified_at,
	) {
	}

	public function isDraft(): bool
	{
		return (bool) ($this->meta['draft'] ?? false);
	}

	public function isPrivate(): bool
	{
		return strtolower((string) ($this->meta['visibility'] ?? 'public')) === 'private';
	}

	public function isInNavigation(): bool
	{
		return ($this->meta['nav'] ?? true) !== false && !$this->isDraft();
	}

	public function order(): int
	{
		return (int) ($this->meta['order'] ?? 1000);
	}

	public function navTitle(): string
	{
		return (string) ($this->meta['nav_title'] ?? $this->title);
	}

	public function type(): string
	{
		return strtolower((string) ($this->meta['type'] ?? 'article'));
	}

	public function reviewedAt(): ?int
	{
		$raw = $this->meta['reviewed'] ?? '';
		if (is_int($raw) && $raw > 0) {
			return $raw;
		}
		$value = trim((string) $raw);
		$time = $value !== '' ? strtotime($value) : false;

		return $time === false ? null : $time;
	}

	public function reviewAfterDays(): int
	{
		return max(1, (int) ($this->meta['review_after'] ?? 180));
	}

	public function isReviewStale(): bool
	{
		$reviewed = $this->reviewedAt();

		return $reviewed !== null && $reviewed + ($this->reviewAfterDays() * 86400) < time();
	}

	public function service(): array
	{
		return is_array($this->meta['service'] ?? null) ? $this->meta['service'] : [];
	}

	public function verifiedWith(): array
	{
		return is_array($this->meta['verified_with'] ?? null) ? $this->meta['verified_with'] : [];
	}

	/** @return list<string> */
	public function keywords(): array
	{
		$values = $this->meta['keywords'] ?? [];
		if (is_string($values)) {
			$values = array_map('trim', explode(',', $values));
		}

		return array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
	}

	/** @return list<string> */
	public function aliases(): array
	{
		$values = $this->meta['aliases'] ?? [];
		if (is_string($values)) {
			$values = [$values];
		}

		return array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
	}

	public function icon(): string
	{
		return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($this->meta['icon'] ?? 'page'))) ?: 'page';
	}

	public function isExcludedFromAi(): bool
	{
		return (bool) ($this->meta['ai_exclude'] ?? false);
	}
}

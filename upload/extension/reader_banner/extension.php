<?php

declare(strict_types=1);

namespace Extension\ReaderBanner;

use System\Engine\ExtensionApplication;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;
use System\Library\Content\Page;

final class Extension implements Contract
{
	private ExtensionApplication $context;
	/** @var array<string,mixed> */
	private array $settings = [];

	public function register(Context $context): void
	{
		$this->context = ExtensionApplication::current();
		$this->settings = $context->settings();
		$context->listen('frontend/page/content/after', function (mixed &$payload): void {
			if (!is_array($payload) || !($payload['page'] ?? null) instanceof Page || !isset($payload['content']) || !is_string($payload['content'])) return;
			$message = trim((string) ($this->settings['message'] ?? ''));
			if ($message === '') return;
			$page = $payload['page'];
			$scope = (string) ($this->settings['page_scope'] ?? 'except_home');
			if (($scope === 'except_home' && $page->url === '/') || ($scope === 'home_only' && $page->url !== '/')) return;
			$color = (string) ($this->settings['accent_color'] ?? '#3b82f6');
			if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) $color = '#3b82f6';
			$icon = $this->icon((string) ($this->settings['icon'] ?? 'info'));
			$dismiss = !empty($this->settings['dismissible']) ? '<button class="grid h-6 w-6 shrink-0 cursor-pointer place-items-center rounded-[5px] border-0 bg-transparent p-0 text-lg leading-none text-[var(--muted)] hover:bg-[color-mix(in_srgb,var(--reader-banner-accent,var(--brand))_12%,transparent)] hover:text-[var(--text)]" type="button" data-reader-banner-dismiss aria-label="Dismiss notice">&times;</button>' : '';
			$banner = '<aside class="my-4 flex items-center justify-between gap-3 rounded-[var(--radius-sm)] border border-[color-mix(in_srgb,var(--reader-banner-accent,var(--brand))_42%,var(--border))] bg-[color-mix(in_srgb,var(--reader-banner-accent,var(--brand))_8%,var(--surface))] px-3 py-2.5 text-[13px] text-[var(--text)] [&>p]:m-0" style="--reader-banner-accent:' . $color . '" data-reader-banner data-reader-banner-key="' . hash('sha256', $message . $color) . '">' . $icon . '<p>' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>' . $dismiss . '</aside>';
			$payload['content'] = (string) ($this->settings['location'] ?? 'below_content') === 'above_content' ? $banner . $payload['content'] : $payload['content'] . $banner;
		}, 'reader_banner.page_content');
	}

	private function icon(string $name): string
	{
		$path = match ($name) {
			'sparkles' => '<path d="m12 3 1.4 5.6L19 10l-5.6 1.4L12 17l-1.4-5.6L5 10l5.6-1.4L12 3ZM19 16l.6 2.4L22 19l-2.4.6L19 22l-.6-2.4L16 19l2.4-.6L19 16Z"/>',
			'check' => '<path d="m5 12 4.5 4.5L19 7"/>',
			'none' => '',
			default => '<circle cx="12" cy="12" r="8"/><path d="M12 10v5M12 7h.01"/>',
		};
		return $path === '' ? '' : '<svg class="h-[18px] w-[18px] shrink-0 fill-none stroke-[var(--reader-banner-accent,var(--brand))] [stroke-linecap:round] [stroke-linejoin:round] [stroke-width:1.8]" viewBox="0 0 24 24" aria-hidden="true">' . $path . '</svg>';
	}
}

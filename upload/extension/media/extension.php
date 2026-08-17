<?php

declare(strict_types=1);

namespace Extension\Media;

use RuntimeException;
use System\Engine\ExtensionApplication;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;
use System\Engine\MediaProcessor;
use System\Library\Image;

final class Extension implements Contract, MediaProcessor
{
	private ExtensionApplication $context;
	/** @var array<string,mixed> */
	private array $settings = [];

	public function register(Context $context): void
	{
		$this->context = ExtensionApplication::current();
		$this->settings = $context->settings();
		$context->service('media.processor', $this);
	}

	public function process(string $path, string $mime): void
	{
		if (!Image::available() || !Image::supports($mime)) return;
		if ($mime === 'image/gif' && empty($this->settings['process_gif'])) return;

		try {
			$image = new Image($path);
		} catch (RuntimeException) {
			return;
		}

		$max_width = max(320, (int) ($this->settings['max_width'] ?? 2400));
		$max_height = max(320, (int) ($this->settings['max_height'] ?? 1600));
		$original_width = $image->width();
		$original_height = $image->height();
		$image->resizeToFit($max_width, $max_height);
		if ($image->width() === $original_width && $image->height() === $original_height) {
			return; // Already within bounds — resizeToFit() left it untouched.
		}

		$jpeg_quality = max(50, min(100, (int) ($this->settings['jpeg_quality'] ?? 85)));
		$webp_quality = max(50, min(100, (int) ($this->settings['webp_quality'] ?? 85)));
		$png_compression = max(0, min(9, (int) ($this->settings['png_compression'] ?? 6)));
		// System\Library\Image fills a resized canvas' transparent background
		// for png/gif/webp alike (skipping only jpeg); the hand-rolled code
		// this replaced filled gif with an opaque white background instead,
		// matching jpeg. That distinction was already unreachable in
		// practice — imagecopyresampled() overwrites the entire canvas for a
		// same-size destination either way, and a palette-indexed GIF's
		// transparent pixels were never faithfully preserved by either
		// implementation. Only reachable when process_gif is explicitly
		// enabled, which defaults to off.
		$image->save($path, $mime === 'image/webp' ? $webp_quality : $jpeg_quality, $mime, $png_compression);
	}
}

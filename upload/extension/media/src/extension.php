<?php

declare(strict_types=1);

namespace Extension\Media;

use System\Engine\Lightdocs\Extension\Application;
use System\Engine\Extension\Context;
use System\Engine\Extension\Contract;
use System\Engine\MediaProcessor;
use System\Library\Image;

final class Extension implements Contract, MediaProcessor
{
	private Application $context;

	public function register(Context $context): void
	{
		$this->context = $this->application($context);
		$context->service('media.processor', $this);
	}

	private function application(Context $context): Application
	{
		$application = $context->capability('lightdocs.application');
		if (!$application instanceof Application) throw new \RuntimeException('Invalid Lightdocs extension capability.');
		return $application;
	}

	public function process(string $path, string $mime): void
	{
		if (!Image::available() || !str_starts_with($mime, 'image/') || !Image::supports($mime)) return;
		if ($mime === 'image/gif' && empty($this->context->settings['process_gif'])) return;
		try {
			$image = new Image($path);
		} catch (\Throwable) {
			return;
		}
		$max_width = max(320, (int) ($this->context->settings['max_width'] ?? 2400));
		$max_height = max(320, (int) ($this->context->settings['max_height'] ?? 1600));
		$scale = min(1, $max_width / $image->width(), $max_height / $image->height());
		if ($scale >= 1) return;
		$jpeg_quality = max(50, min(100, (int) ($this->context->settings['jpeg_quality'] ?? 85)));
		$webp_quality = max(50, min(100, (int) ($this->context->settings['webp_quality'] ?? 85)));
		$png_compression = max(0, min(9, (int) ($this->context->settings['png_compression'] ?? 6)));
		$image->resizeToFit($max_width, $max_height)->save($path, $mime === 'image/webp' ? $webp_quality : $jpeg_quality, $mime, $png_compression);
	}
}

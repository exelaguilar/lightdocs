<?php

declare(strict_types=1);

namespace Extension\Media;

use System\Engine\ExtensionContext;
use System\Engine\ExtensionInterface;
use System\Engine\ExtensionRegistrarInterface;
use System\Engine\MediaProcessor;

final class Extension implements ExtensionInterface, MediaProcessor
{
	public function __construct(private readonly ExtensionContext $context)
	{
	}

	public function name(): string
	{
		return 'media';
	}

	public function register(ExtensionRegistrarInterface $extensions): void
	{
		$extensions->service('media.processor', $this);
	}

	public function process(string $path, string $mime): void
	{
		if (!function_exists('imagecreatefromjpeg') || !str_starts_with($mime, 'image/')) return;
		if ($mime === 'image/gif' && empty($this->context->settings['process_gif'])) return;
		$dimensions = @getimagesize($path);
		if (!is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1])) return;
		$max_width = max(320, (int) ($this->context->settings['max_width'] ?? 2400));
		$max_height = max(320, (int) ($this->context->settings['max_height'] ?? 1600));
		$scale = min(1, $max_width / $dimensions[0], $max_height / $dimensions[1]);
		if ($scale >= 1) return;
		$width = max(1, (int) round($dimensions[0] * $scale));
		$height = max(1, (int) round($dimensions[1] * $scale));
		$source = match ($mime) {
			'image/jpeg' => @imagecreatefromjpeg($path),
			'image/png' => @imagecreatefrompng($path),
			'image/gif' => @imagecreatefromgif($path),
			'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
			default => false,
		};
		if ($source === false) return;
		$target = imagecreatetruecolor($width, $height);
		if ($mime === 'image/png' || $mime === 'image/webp') {
			imagealphablending($target, false);
			imagesavealpha($target, true);
			$transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
			imagefilledrectangle($target, 0, 0, $width, $height, $transparent);
		} else {
			$background = imagecolorallocate($target, 255, 255, 255);
			imagefilledrectangle($target, 0, 0, $width, $height, $background);
		}
		imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
		$jpeg_quality = max(50, min(100, (int) ($this->context->settings['jpeg_quality'] ?? 85)));
		$webp_quality = max(50, min(100, (int) ($this->context->settings['webp_quality'] ?? 85)));
		$png_compression = max(0, min(9, (int) ($this->context->settings['png_compression'] ?? 6)));
		match ($mime) {
			'image/jpeg' => imagejpeg($target, $path, $jpeg_quality),
			'image/png' => imagepng($target, $path, $png_compression),
			'image/gif' => imagegif($target, $path),
			'image/webp' => function_exists('imagewebp') ? imagewebp($target, $path, $webp_quality) : false,
			default => false,
		};
		imagedestroy($source);
		imagedestroy($target);
	}
}

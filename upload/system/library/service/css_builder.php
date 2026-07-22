<?php

declare(strict_types=1);

namespace System\Library\Service;

use RuntimeException;
use System\Library\AssetPublisher;

final class CssBuilder
{
	public function __construct(private readonly array $config, private readonly AssetPublisher $publisher)
	{
	}

	public function build(): int
	{
		$application_root = rtrim((string) $this->config['application_root'], '/\\');
		$shared_source = $application_root . '/app.css';
		$shared_sources = $this->collectSources([
			$application_root . '/system/library/content',
		]);

		$bundles = [
			'admin' => [
				$application_root . '/admin/view/stylesheet/admin.css',
				'admin.css',
				[
					$application_root . '/admin/view/template',
					$application_root . '/admin/view/javascript',
					$application_root . '/extension',
				],
			],
			'frontend' => [
				$application_root . '/frontend/view/stylesheet/front.css',
				'frontend.css',
				[
					$application_root . '/frontend/view/template',
					$application_root . '/frontend/view/javascript',
					$application_root . '/extension/reader_banner',
				],
			],
		];
		$total_bytes = 0;
		$this->publisher->publish(
			function (string $staging) use ($bundles, $shared_sources, $shared_source, &$total_bytes): void {
				foreach ($bundles as $bundle) {
					$sources = [...$shared_sources, ...$this->collectSources($bundle[2])];
					$total_bytes += $this->buildBundle($shared_source, $bundle[0], $staging . '/' . $bundle[1], $sources);
				}
			},
			static function (string $staging, array $manifest): void {
				foreach (['admin.css', 'frontend.css'] as $logical) {
					$url = (string)($manifest['assets'][$logical] ?? '');
					$path = $staging . '/' . basename($url);
					if ($url === '' || !is_file($path) || filesize($path) < 1000) {
						throw new RuntimeException('The generated ' . $logical . ' bundle failed validation.');
					}
				}
			}
		);

		return $total_bytes;
	}

	/** @return list<string> */
	private function collectSources(array $roots): array
	{
		$sources = [];

		foreach ($roots as $root) {
			if (!is_dir($root)) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $file) {
				if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'twig', 'js'], true)) {
					continue;
				}

				$content = file_get_contents($file->getPathname());
				if ($content !== false) {
					if ($file->getExtension() === 'twig') {
						preg_match_all('/\bclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $content, $matches, PREG_SET_ORDER);
						$fragments = [];
						foreach ($matches as $match) {
							$classes = (string) ($match[1] !== '' ? $match[1] : ($match[2] ?? ''));
							preg_match_all('/\'([^\']+)\'/', $classes, $dynamic);
							$literal = preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' ', $classes) ?? $classes;
							$fragments[] = trim($literal . ' ' . implode(' ', $dynamic[1] ?? []));
						}
						$content = implode("\n", array_map(static fn (string $classes): string => '<div class="' . $classes . '"></div>', $fragments));
					}
					$sources[] = $content;
				}
			}
		}

		return $sources;
	}

	/** @param list<string> $sources */
	private function buildBundle(string $shared_source, string $bundle_source, string $output_path, array $sources): int
	{
		$shared = file_get_contents($shared_source);
		$bundle = file_get_contents($bundle_source);
		if ($shared === false || $bundle === false) {
			throw new \RuntimeException('Unable to read CSS bundle sources.');
		}

		$temp_stub = tempnam(dirname($shared_source), '.lightdocs-css-');
		if ($temp_stub === false) {
			throw new \RuntimeException('Unable to create temporary CSS bundle source.');
		}
		// TailwindPHP's resolveImportPaths() only reads paths ending in `.css`;
		// tempnam() can't produce that suffix itself, so alias the unique name
		// it gave us onto a `.css`-suffixed path before handing it to Tailwind.
		$temp_path = $temp_stub . '.css';

		try {
			file_put_contents($temp_path, $shared . "\n");
			$source = implode("\n", $sources);
			$string_candidates = [];
			foreach ($sources as $candidate_source) {
				array_push($string_candidates, ...\TailwindPHP\Tailwind::extractCandidatesFromStrings($candidate_source));
			}
			$string_candidates = array_values(array_unique($string_candidates));
			if ($string_candidates !== []) {
				$source .= "\n<div class=\"" . implode(' ', $string_candidates) . "\"></div>";
			}
			$output = \TailwindPHP\Tailwind::generate([
				'content' => $source,
				'importPaths' => $temp_path,
				'minify' => true,
			]);
			if (file_put_contents($output_path, $output . "\n" . $bundle . "\n", LOCK_EX) === false) {
				throw new RuntimeException('Unable to write the compiled CSS bundle.');
			}

			return strlen($output) + strlen($bundle);
		} finally {
			@unlink($temp_stub);
			@unlink($temp_path);
		}
	}
}

<?php

declare(strict_types=1);

namespace System\Library\Service;

final class CssBuilder
{
	public function __construct(private readonly array $config)
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
				$application_root . '/admin/view/stylesheet/app.min.css',
				[
					$application_root . '/admin/view/template',
					$application_root . '/admin/view/javascript',
					$application_root . '/extension',
				],
			],
			'frontend' => [
				$application_root . '/frontend/view/stylesheet/front.css',
				$application_root . '/frontend/view/stylesheet/front.min.css',
				[
					$application_root . '/frontend/view/template',
					$application_root . '/frontend/view/javascript',
					$application_root . '/extension/reader_banner',
				],
			],
		];
		$total_bytes = 0;

		foreach ($bundles as $bundle) {
			$sources = [...$shared_sources, ...$this->collectSources($bundle[2])];
			$total_bytes += $this->buildBundle($shared_source, $bundle[0], $bundle[1], $sources);
		}

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
				if (!$file->isFile() || !in_array($file->getExtension(), ['php', 'js'], true)) {
					continue;
				}

				$content = file_get_contents($file->getPathname());
				if ($content !== false) {
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
			file_put_contents($output_path, $output . "\n" . $bundle . "\n");

			return strlen($output) + strlen($bundle);
		} finally {
			@unlink($temp_stub);
			@unlink($temp_path);
		}
	}
}

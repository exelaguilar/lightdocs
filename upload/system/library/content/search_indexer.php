<?php

declare(strict_types=1);

namespace System\Library\Content;

final class SearchIndexer implements SearchService
{
	public function __construct(
		private readonly ContentRepository $repository,
		private readonly MarkdownRenderer $renderer,
		private readonly string $path,
	) {
	}

	public function build(): array
	{
		$documents = [];
		foreach ($this->repository->all() as $page) {
			$rendered = $this->renderer->render($page);
			$documents = [...$documents, ...$this->records($page, $rendered)];
		}
		if (!is_dir(dirname($this->path))) {
			mkdir(dirname($this->path), 0775, true);
		}
		file_put_contents($this->path, json_encode($documents, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
		file_put_contents($this->path . '.fingerprint', $this->fingerprint(), LOCK_EX);

		return $documents;
	}

	/** @return list<array<string,mixed>> */
	public function records(Page $page, RenderedDocument $rendered): array
	{
		$section = $this->repository->sectionFor($page);
		$breadcrumbs = implode(' / ', array_map(static fn (array $crumb): string => $crumb['title'], $this->repository->breadcrumbs($page)));
		$common = [
			'page' => $page->url,
			'section' => $section['title'] ?? '',
			'sectionPath' => $section['path'] ?? '',
			'breadcrumbs' => $breadcrumbs,
			'keywords' => $page->keywords(),
			'aliases' => $page->aliases(),
			'type' => $page->type(),
		];
		$records = [[...$common,
			'kind' => 'page', 'url' => $page->url, 'title' => $page->title,
			'description' => $page->description, 'text' => mb_substr($rendered->plain_text, 0, 12000),
		]];
		foreach ($rendered->headings as $heading) {
			$records[] = [...$common,
				'kind' => 'heading', 'url' => $page->url . '#' . $heading['id'], 'title' => $heading['title'],
				'description' => $page->title, 'text' => $page->title . ' ' . $heading['title'] . ' ' . implode(' ', $page->keywords()),
			];
		}

		return $records;
	}

	public function read(): array
	{
		if (!is_file($this->path)) {
			return $this->build();
		}
		$fingerprint_path = $this->path . '.fingerprint';
		if (!is_file($fingerprint_path) || !hash_equals(trim((string) file_get_contents($fingerprint_path)), $this->fingerprint())) {
			return $this->build();
		}

		return json_decode((string) file_get_contents($this->path), true) ?: [];
	}

	private function fingerprint(): string
	{
		$sources = [];
		foreach ($this->repository->all(true, true) as $page) {
			$sources[] = [$page->relative_path, $page->modified_at, (int) @filesize($page->source_path)];
		}
		$sources[] = ['sections', $this->repository->sections()];
		$sources[] = [
			'renderer',
			(int) @filemtime(__DIR__ . '/markdown_renderer.php'),
			(int) @filemtime(__DIR__ . '/directive_processor.php'),
		];

		return hash('sha256', json_encode($sources, JSON_UNESCAPED_SLASHES));
	}
}

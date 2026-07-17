<?php

declare(strict_types=1);

namespace System\Library\Content;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Content-owned glossary terms used by the reader and Content Studio.
 */
final class Glossary
{
	private array $terms = [];

	public function __construct(private readonly string $path)
	{
		$this->terms = $this->load();
	}

	public function find(string $slug): ?array
	{
		return $this->terms[$slug] ?? null;
	}

	public function all(): array
	{
		return $this->terms;
	}

	public function save(string $slug, string $term, string $definition, array $aliases = [], ?string $previous_slug = null): string
	{
		$slug = strtolower(trim($slug));
		$term = trim($term);
		$definition = trim($definition);
		if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
			throw new RuntimeException('The glossary reference must use a lowercase slug.');
		}
		if ($term === '' || $definition === '') {
			throw new RuntimeException('A glossary term and definition are required.');
		}
		$previous_slug = $previous_slug === null ? null : strtolower(trim($previous_slug));
		if ($previous_slug !== null && $previous_slug !== $slug) {
			throw new RuntimeException('Glossary references cannot be renamed because Markdown may already link to them.');
		}
		if ($previous_slug === null && isset($this->terms[$slug])) {
			throw new RuntimeException('That glossary reference already exists.');
		}
		$aliases = array_values(array_unique(array_filter(array_map(static fn (mixed $alias): string => trim((string) $alias), $aliases))));
		$this->terms[$slug] = ['slug' => $slug, 'term' => $term, 'definition' => $definition, 'aliases' => $aliases];
		ksort($this->terms, SORT_NATURAL | SORT_FLAG_CASE);
		$this->persist();

		return $slug;
	}

	public function delete(string $slug): void
	{
		$slug = strtolower(trim($slug));
		if (!isset($this->terms[$slug])) {
			throw new RuntimeException('The glossary term no longer exists.');
		}
		unset($this->terms[$slug]);
		$this->persist();
	}

	/**
	 * A deliberately small payload for the editor's inline reference suggestion.
	 */
	public function editorTerms(): array
	{
		return array_values(array_map(static fn (array $term): array => [
			'slug' => $term['slug'],
			'term' => $term['term'],
			'aliases' => $term['aliases'],
		], $this->terms));
	}

	private function load(): array
	{
		if (!is_file($this->path)) {
			return [];
		}

		$data = Yaml::parseFile($this->path) ?? [];
		if (!is_array($data)) {
			throw new RuntimeException('Glossary data must be a YAML mapping.');
		}
		$data = is_array($data['terms'] ?? null) ? $data['terms'] : $data;
		$terms = [];
		foreach ($data as $slug => $value) {
			$slug = strtolower(trim((string) $slug));
			if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
				throw new RuntimeException('Glossary keys must use lowercase slugs.');
			}
			if (!is_array($value)) {
				throw new RuntimeException('Glossary term "' . $slug . '" must be a YAML mapping.');
			}
			$term = trim((string) ($value['term'] ?? ''));
			$definition = trim((string) ($value['definition'] ?? ''));
			if ($term === '' || $definition === '') {
				throw new RuntimeException('Glossary term "' . $slug . '" needs a term and definition.');
			}
			$aliases = is_array($value['aliases'] ?? null) ? $value['aliases'] : [];
			$aliases = array_values(array_unique(array_filter(array_map(static fn (mixed $alias): string => trim((string) $alias), $aliases))));
			$terms[$slug] = ['slug' => $slug, 'term' => $term, 'definition' => $definition, 'aliases' => $aliases];
		}
		ksort($terms, SORT_NATURAL | SORT_FLAG_CASE);

		return $terms;
	}

	private function persist(): void
	{
		$directory = dirname($this->path);
		if (!is_dir($directory) || !is_writable($directory)) {
			throw new RuntimeException('The canonical content directory is not writable.');
		}
		$data = [];
		foreach ($this->terms as $slug => $term) {
			$data[$slug] = ['term' => $term['term'], 'definition' => $term['definition']];
			if ($term['aliases'] !== []) {
				$data[$slug]['aliases'] = $term['aliases'];
			}
		}
		$yaml = "# Canonical shared terms for the reader glossary and Content Studio.\n";
		$yaml .= Yaml::dump($data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
		if (file_put_contents($this->path, $yaml, LOCK_EX) === false) {
			throw new RuntimeException('The glossary could not be saved.');
		}
	}
}

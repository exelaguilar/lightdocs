<?php

declare(strict_types=1);

namespace System\Engine;

use Composer\Semver\Comparator;
use RuntimeException;

final class ExtensionCatalog
{
	/** @var list<ExtensionCatalogEntry> */
	private array $entries;

	/** @param list<ExtensionCatalogEntry> $entries */
	public function __construct(array $entries)
	{
		$seen = [];
		foreach ($entries as $entry) {
			if (!$entry instanceof ExtensionCatalogEntry) throw new RuntimeException('Extension catalog entries must be typed.');
			$key = $entry->name() . '@' . $entry->version() . '#' . $entry->channel();
			if (isset($seen[$key])) throw new RuntimeException('Duplicate extension catalog entry: ' . $key);
			$seen[$key] = true;
		}
		usort($entries, static function (ExtensionCatalogEntry $a, ExtensionCatalogEntry $b): int {
			$name = strcmp($a->name(), $b->name());
			if ($name !== 0) return $name;
			$version = version_compare($b->version(), $a->version());
			return $version !== 0 ? $version : strcmp($a->channel(), $b->channel());
		});
		$this->entries = array_values($entries);
	}

	/** @return list<ExtensionCatalogEntry> */
	public function entries(): array { return $this->entries; }

	/** @param list<string> $channels */
	public function updateFor(ExtensionInstallation $installation, array $channels = ['stable']): ?ExtensionCatalogEntry
	{
		foreach ($this->entries as $entry) {
			if ($entry->name() !== $installation->name() || !in_array($entry->channel(), $channels, true)) continue;
			if (Comparator::greaterThan($entry->version(), $installation->version())) return $entry;
		}

		return null;
	}
}

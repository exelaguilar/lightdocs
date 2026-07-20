<?php

declare(strict_types=1);

namespace System\Engine;

use Composer\Semver\Semver;
use RuntimeException;

final class ExtensionDependencyResolver
{
	/**
	 * Validate every context represented by the manifests, plus the global
	 * context in which only context-neutral extensions participate.
	 *
	 * @param array<string,ExtensionManifest>      $manifests
	 * @param array<string,ExtensionInstallation> $installations
	 */
	public function assertValidState(array $manifests, array $installations): void
	{
		$contexts = ['__tinymvc_global__' => true];
		foreach ($manifests as $manifest) {
			foreach ($manifest->contexts() as $context) $contexts[$context] = true;
		}
		foreach (array_keys($contexts) as $context) $this->resolve($manifests, $installations, $context);
	}

	/**
	 * @param array<string,ExtensionManifest>     $manifests
	 * @param array<string,ExtensionInstallation> $installations
	 * @return list<string>
	 */
	public function resolve(array $manifests, array $installations, string $context): array
	{
		$active = [];
		foreach ($manifests as $name => $manifest) {
			$installation = $installations[$name] ?? null;
			if ($installation === null || !$installation->enabled() || $installation->status() === ExtensionInstallation::BROKEN) continue;
			if ($manifest->contexts() !== [] && !in_array($context, $manifest->contexts(), true)) continue;
			$active[$name] = $manifest;
		}

		$edges = array_fill_keys(array_keys($active), []);
		$incoming = array_fill_keys(array_keys($active), 0);
		foreach ($active as $name => $manifest) {
			$dependencies = $manifest->dependencies();
			foreach ($dependencies['requires'] as $dependency => $constraint) {
				if (!isset($active[$dependency])) {
					throw new RuntimeException('Required extension dependency is unavailable: ' . $dependency . ' (' . $name . ')');
				}
				try {
					$matches = Semver::satisfies($active[$dependency]->version(), $constraint);
				} catch (\UnexpectedValueException $exception) {
					throw new RuntimeException('Invalid extension dependency constraint: ' . $name . ' requires ' . $dependency . ' ' . $constraint, 0, $exception);
				}
				if (!$matches) {
					throw new RuntimeException(sprintf('Extension dependency version mismatch: %s requires %s %s; found %s.', $name, $dependency, $constraint, $active[$dependency]->version()));
				}
				$this->edge($dependency, $name, $edges, $incoming);
			}
			foreach ($dependencies['conflicts'] as $conflict => $constraint) {
				if (!isset($active[$conflict])) continue;
				try {
					$matches = $constraint === '*' || Semver::satisfies($active[$conflict]->version(), $constraint);
				} catch (\UnexpectedValueException $exception) {
					throw new RuntimeException('Invalid extension conflict constraint: ' . $name . ' conflicts with ' . $conflict . ' ' . $constraint, 0, $exception);
				}
				if ($matches) {
					throw new RuntimeException(sprintf('Extension conflict: %s conflicts with %s %s.', $name, $conflict, $constraint));
				}
			}
			foreach ($dependencies['load_after'] as $dependency) {
				if (isset($active[$dependency])) $this->edge($dependency, $name, $edges, $incoming);
			}
		}

		$ready = array_keys(array_filter($incoming, static fn (int $count): bool => $count === 0));
		sort($ready, SORT_STRING);
		$order = [];
		while ($ready !== []) {
			$name = array_shift($ready);
			$order[] = $name;
			$targets = $edges[$name];
			sort($targets, SORT_STRING);
			foreach ($targets as $target) {
				$incoming[$target]--;
				if ($incoming[$target] === 0) {
					$ready[] = $target;
					sort($ready, SORT_STRING);
				}
			}
		}
		if (count($order) !== count($active)) {
			$cycle = array_keys(array_filter($incoming, static fn (int $count): bool => $count > 0));
			sort($cycle, SORT_STRING);
			throw new RuntimeException('Extension dependency cycle: ' . implode(', ', $cycle));
		}

		return $order;
	}

	/** @param array<string,list<string>> $edges @param array<string,int> $incoming */
	private function edge(string $from, string $to, array &$edges, array &$incoming): void
	{
		if (in_array($to, $edges[$from], true)) return;
		$edges[$from][] = $to;
		$incoming[$to]++;
	}
}

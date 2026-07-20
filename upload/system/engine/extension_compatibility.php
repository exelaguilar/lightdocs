<?php

declare(strict_types=1);

namespace System\Engine;

use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use RuntimeException;

final class ExtensionCompatibility
{
	/** @param array<string,string> $platformVersions
	 *  @return array<string,string>
	 */
	public function evaluate(ExtensionManifest $manifest, array $platformVersions): array
	{
		$failures = [];
		foreach ($manifest->requirements() as $platform => $constraint) {
			if (!isset($platformVersions[$platform])) {
				$failures[$platform] = 'Required platform version is unavailable.';
				continue;
			}
			try {
				$matches = Semver::satisfies($platformVersions[$platform], $constraint);
			} catch (\UnexpectedValueException $exception) {
				$failures[$platform] = 'Invalid version constraint: ' . $constraint;
				continue;
			}
			if (!$matches) {
				$failures[$platform] = sprintf('Version %s does not satisfy %s.', $platformVersions[$platform], $constraint);
			}
		}

		return $failures;
	}

	/** @param array<string,string> $platformVersions */
	public function assertCompatible(ExtensionManifest $manifest, array $platformVersions): void
	{
		$failures = $this->evaluate($manifest, $platformVersions);
		if ($failures !== []) {
			throw new RuntimeException('Extension is incompatible: ' . $manifest->name() . ' (' . implode('; ', $failures) . ')');
		}
	}

	public function assertUpgrade(string $currentVersion, ExtensionManifest $candidate): void
	{
		try {
			$newer = Comparator::greaterThan($candidate->version(), $currentVersion);
		} catch (\UnexpectedValueException $exception) {
			throw new RuntimeException('Extension upgrade versions are invalid.', 0, $exception);
		}
		if (!$newer) {
			throw new RuntimeException(sprintf('Extension upgrade must increase the version from %s; received %s.', $currentVersion, $candidate->version()));
		}
	}
}

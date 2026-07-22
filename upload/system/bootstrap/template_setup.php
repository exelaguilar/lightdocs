<?php

declare(strict_types=1);

namespace System\Bootstrap;

use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Library\Template;

/**
 * Configures Lightdocs' template environment for both HTTP and CLI builds.
 *
 * TinyMVC owns the Template facade and Twig adaptor. Lightdocs owns the
 * functions that expose application data to its templates.
 */
final class TemplateSetup implements Contract
{
	public function register(Registry $registry): void
	{
		self::configure($registry->get('template'));
	}

	public function boot(Registry $registry): void
	{
	}

	public static function configure(Template $template): void
	{
		// TinyMVC intentionally exposes a small adaptor-neutral registration API.
		// These three PHP-named functions are temporary compatibility shims for
		// converted admin templates; new Twig must use native filters/operators.
		$template->addFunction('e', static fn (mixed $value): string => (string) $value);
		$template->addFunction('isset', static fn (mixed $value): bool => $value !== null);
		$template->addFunction('implode', static fn (string $separator, array $values): string => implode($separator, $values));
		$template->addFunction('asset_version', static function (string $asset): string {
			$path = (defined('DIR_ROOT') ? DIR_ROOT : '') . ltrim($asset, '/');
			return $asset . '?v=' . (is_file($path) ? (string) filemtime($path) : '1');
		});
	}
}

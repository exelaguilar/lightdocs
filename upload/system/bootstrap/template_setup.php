<?php

declare(strict_types=1);

namespace Lightdocs\Bootstrap;

use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Library\Content\ContentEditor;
use System\Library\Content\ContentHealth;
use System\Library\Service\CssBuilder;
use System\Library\Service\ExportService;
use System\Library\Service\StaticSiteBuilder;
use System\Library\Template;

final class TemplateSetup implements Contract
{
	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$repository = $registry->get('repository');
		$directives = $registry->get('directives');
		$services = (array)$registry->get('extension_services');
		$registry->set('health', new ContentHealth($repository, (string)$config->get('content_dir'), (string)$config->get('upload_dir'), $directives));
		$registry->set('content_editor', new ContentEditor(
			(string)$config->get('content_dir'),
			(string)$config->get('revision_dir'),
			(string)$config->get('upload_dir'),
			$services['media.processor'] ?? null,
			$services['storage.assets'] ?? null,
		));
		$publisher = $registry->get('asset_publisher');
		$registry->set('css', new CssBuilder($config->all(), $publisher));
		// The Kernel template serves the active request context (admin or
		// frontend). Static exports always render the public frontend, so they
		// require a separate adaptor with the public template root.
		$public_template = new Template((string)$config->get('template_engine'), $config);
		$public_template->addPath(dirname(__DIR__, 2) . '/frontend/view/template/');
		$public_template->addGlobal('csp_nonce', (string)$config->get('csp_nonce', ''));
		$registry->set('public_template', $public_template);
		$builder = new StaticSiteBuilder($config->all(), $repository, $registry->get('renderer'), $registry->get('search'), $public_template, $registry->get('event'));
		$registry->set('builder', $builder);
		$registry->set('exports', new ExportService($config->all(), $builder));
	}
}

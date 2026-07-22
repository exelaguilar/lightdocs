<?php

declare(strict_types=1);

namespace System\Bootstrap;

use System\Engine\CallbackAction;
use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Library\Content\AssetRepository;
use System\Library\Content\ContentEditor;
use System\Library\Content\ContentHealth;
use System\Library\Content\ContentImporter;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Content\Glossary;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\NavigationManager;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SiteData;
use System\Library\Content\SnippetRepository;
use System\Library\Feedback;
use System\Library\Service\CssBuilder;
use System\Library\Service\ExportService;
use System\Library\Service\SiteSettings;
use System\Library\Service\StaticSiteBuilder;
use System\Library\Template;
use System\Model\ContentIndex;
use System\Model\SqliteSearchService;

/** Composes Lightdocs' documentation-domain services around TinyMVC primitives. */
final class ContentSetup implements Contract
{
	public function register(Registry $registry): void
	{
		$config = $registry->get('config');
		$repository = new ContentRepository($config->get('content_dir'));
		$registry->set('repository', $repository);
		$directives = new DirectiveRegistry((array)$config->get('directives'));
		$registry->set('directives', $directives);
		$glossary = new Glossary($config->get('glossary_file'));
		$registry->set('glossary', $glossary);
		$renderer = new MarkdownRenderer((bool)$config->get('raw_html'), SiteData::load($config->get('data_file')), $config->get('content_dir'), $directives, $glossary);
		$registry->set('renderer', $renderer);
		$registry->set('index', new ContentIndex($registry));
		$registry->set('json_search', new SearchIndexer($repository, $renderer, $config->get('cache_dir') . '/search-index.json'));
		$registry->set('search', new SqliteSearchService($registry));
		$registry->set('feedback', new Feedback($registry->get('db')));
		$registry->set('settings', new SiteSettings($registry->get('event'), $config->get('settings_paths')['site'], $config->get('settings_paths')['theme'], $config->get('environment_file')));
	}

	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$repository = $registry->get('repository');
		$renderer = $registry->get('renderer');
		$directives = $registry->get('directives');
		$extensions = $registry->get('extensions');
		$extension_services = $extensions->services();
		$registry->set('health', new ContentHealth($repository, $config->get('content_dir'), $config->get('upload_dir'), $directives));
		$registry->set('content_editor', new ContentEditor($config->get('content_dir'), $config->get('revision_dir'), $config->get('upload_dir'), $extension_services['media.processor'] ?? null, $extension_services['storage.assets'] ?? null));
		$registry->set('asset_repository', new AssetRepository($config->get('upload_dir'), $repository));
		$registry->set('snippets', new SnippetRepository($config->get('content_dir'), $repository));
		$registry->set('navigation', new NavigationManager($config->get('content_dir')));
		$registry->set('importer', new ContentImporter($config->get('content_dir')));
		$registry->set('css', new CssBuilder($config->all(), $registry->get('asset_publisher')));

		$public_template = new Template($config->get('template_engine'), $config);
		$public_template->addPath(dirname(__DIR__, 2) . '/frontend/view/template/');
		$public_template->addGlobal('csp_nonce', $config->get('csp_nonce'));
		TemplateSetup::configure($public_template);
		$registry->set('public_template', $public_template);
		$builder = new StaticSiteBuilder($config->all(), $repository, $renderer, $registry->get('search'), $public_template, $registry->get('event'));
		$registry->set('builder', $builder);
		$registry->set('exports', new ExportService($config->all(), $builder));

		$cache = $registry->get('cache');
		$index = $registry->get('index');
		$registry->get('event')->register('content.changed', new CallbackAction(static function () use ($cache, $repository, $index): void {
			$cache->clear();
			$repository->refresh();
			$index->sync(true);
		}, 'core.content_changed'), 0, 'core.content_changed', 'lightdocs');
	}
}

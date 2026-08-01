<?php

declare(strict_types=1);

namespace Lightdocs\Bootstrap;

use System\Engine\Provider\Contract;
use System\Engine\Registry;
use System\Engine\CallbackAction;
use System\Library\Content\AssetRepository;
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
use System\Library\Service\SiteSettings;
use System\Model\ContentIndex;
use System\Model\SqliteSearchService;

final class ContentSetup implements Contract
{
	public function boot(Registry $registry): void
	{
		$config = $registry->get('config');
		$repository = new ContentRepository((string)$config->get('content_dir'));
		$registry->set('repository', $repository);
		$directives = new DirectiveRegistry((array)$config->get('directives'));
		$registry->set('directives', $directives);
		$glossary = new Glossary((string)$config->get('glossary_file'));
		$registry->set('glossary', $glossary);
		$renderer = new MarkdownRenderer(
			(bool)$config->get('raw_html'),
			SiteData::load((string)$config->get('data_file')),
			(string)$config->get('content_dir'),
			$directives,
			$glossary,
		);
		$registry->set('renderer', $renderer);
		$index = new ContentIndex($registry);
		$registry->set('index', $index);
		$registry->set('json_search', new SearchIndexer($repository, $renderer, (string)$config->get('cache_dir') . '/search-index.json'));
		$registry->set('search', new SqliteSearchService($registry));
		$registry->set('feedback', new Feedback($registry->get('db')));
		$registry->set('settings', new SiteSettings(
			$registry->get('event'),
			(string)$config->get('settings_paths')['site'],
			(string)$config->get('settings_paths')['theme'],
			(string)$config->get('environment_file'),
		));
		$registry->set('asset_repository', new AssetRepository((string)$config->get('upload_dir'), $repository));
		$registry->set('snippets', new SnippetRepository((string)$config->get('content_dir'), $repository));
		$registry->set('navigation', new NavigationManager((string)$config->get('content_dir')));
		$registry->set('importer', new ContentImporter((string)$config->get('content_dir')));
		$cache = $registry->get('cache');
		$event = $registry->get('event');
		$event->register('content.changed', new CallbackAction(static function ($payload) use ($cache, $repository, $index): void {
			$cache->clear();
			$repository->refresh();
			$index->sync(true);
		}, 'core.content_changed'));
	}
}

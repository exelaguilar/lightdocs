<?php

declare(strict_types=1);

namespace System\Model;

use System\Library\Content\Page;
use System\Library\Content\RenderedDocument;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SearchService;
use System\Engine\Model;
use System\Engine\Registry;

final class SqliteSearchService extends Model implements SearchService
{
	private ContentIndex $index;
	private SearchIndexer $record_builder;

	public function __construct(Registry $registry)
	{
		parent::__construct($registry);
		$this->index = $registry->get('index');
		$this->record_builder = $registry->get('json_search');
	}

	public function build(): array
	{
		$this->index->sync(true);
		return $this->index->records();
	}

	public function read(): array
	{
		return $this->index->records();
	}

	public function search(string $query, bool $include_private = false, int $limit = 20): array
	{
		return $this->index->search($query, $include_private, $limit);
	}

	public function records(Page $page, RenderedDocument $rendered): array
	{
		return $this->record_builder->records($page, $rendered);
	}
}

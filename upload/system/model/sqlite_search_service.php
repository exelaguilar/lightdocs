<?php

declare(strict_types=1);

namespace System\Model;

use System\Library\Content\Page;
use System\Library\Content\RenderedDocument;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SearchService;
use System\Library\DB;
use System\Engine\Event;
use System\Engine\Model;

final class SqliteSearchService extends Model implements SearchService
{
	public function __construct(DB $database, Event $events, private readonly ContentIndex $index, private readonly SearchIndexer $record_builder)
	{
		parent::__construct($database, $events);
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

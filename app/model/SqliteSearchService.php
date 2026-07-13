<?php

declare(strict_types=1);

namespace Lightdocs\App\Model;

use Lightdocs\App\Library\Page;
use Lightdocs\App\Library\RenderedDocument;
use Lightdocs\App\Library\SearchIndexer;
use Lightdocs\App\Library\SearchService;
use Lightdocs\System\Library\Database;
use Lightdocs\System\Engine\EventDispatcher;
use Lightdocs\System\Engine\Model;

final class SqliteSearchService extends Model implements SearchService
{
    public function __construct(Database $database, EventDispatcher $events, private readonly ContentIndex $index, private readonly SearchIndexer $recordBuilder)
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

    public function search(string $query, bool $includePrivate = false, int $limit = 20): array
    {
        return $this->index->search($query, $includePrivate, $limit);
    }

    public function records(Page $page, RenderedDocument $rendered): array
    {
        return $this->recordBuilder->records($page, $rendered);
    }
}

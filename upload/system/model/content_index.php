<?php

declare(strict_types=1);

namespace System\Model;

use System\Library\Content\ContentRepository;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\Page;
use System\Library\DB;
use System\Engine\Event;
use System\Engine\Model;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use Symfony\Component\Yaml\Yaml;

final class ContentIndex extends Model
{
	private PDO $pdo;

	public function __construct(
		DB $database,
		Event $events,
		private readonly ContentRepository $repository,
		private readonly MarkdownRenderer $renderer,
		private readonly string $content_dir,
		private readonly string $upload_dir,
	) {
		parent::__construct($database, $events);
		$this->pdo = $this->db;
	}

	public function sync(bool $force = false): array
	{
		$fingerprint = $this->fingerprint();
		if (!$force && $this->meta('content_fingerprint') === $fingerprint) {
			return $this->stats();
		}

		$this->pdo->beginTransaction();
		try {
			foreach (['asset_usage', 'snippet_usage', 'document_keywords', 'aliases', 'keywords', 'links', 'headings', 'assets', 'snippets', 'documents', 'settings'] as $table) {
				$this->pdo->exec('DELETE FROM ' . $table);
			}
			$this->indexSnippets();
			$this->indexAssets();
			$this->indexSettings();
			foreach ($this->repository->all(true, true) as $page) {
				$this->indexPage($page);
			}
			if ($this->hasFts()) {
				$this->pdo->exec("INSERT INTO documents_fts(documents_fts) VALUES('rebuild')");
			}
			$this->setMeta('content_fingerprint', $fingerprint);
			$this->setMeta('last_synced_at', (string) time());
			$this->pdo->commit();
		} catch (\Throwable $exception) {
			if ($this->pdo->inTransaction()) $this->pdo->rollBack();
			throw $exception;
		}

		$stats = $this->stats();
		$this->events->dispatch('index.rebuilt', $stats);
		return $stats;
	}

	/** @return list<array<string,mixed>> */
	public function search(string $query, bool $include_private = false, int $limit = 20): array
	{
		$this->sync();
		$query = trim($query);
		if ($query === '') {
			return [];
		}
		$privacy = $include_private ? '' : " AND d.visibility = 'public' AND d.draft = 0";
		if ($this->hasFts()) {
			$terms = array_values(array_filter(preg_split('/[^\pL\pN_-]+/u', $query) ?: []));
			$match = implode(' AND ', array_map(static fn (string $term): string => '"' . str_replace('"', '""', $term) . '"*', $terms));
			if ($match !== '') {
				$sql = "SELECT d.url, d.title, d.description, d.type, d.keywords, bm25(documents_fts, 7.0, 3.0, 2.0, 1.0) AS rank
						FROM documents_fts JOIN documents d ON d.id = documents_fts.rowid
						WHERE documents_fts MATCH :query{$privacy} ORDER BY rank LIMIT :limit";
				$statement = $this->pdo->prepare($sql);
				$statement->bindValue(':query', $match);
				$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
				$statement->execute();
				return array_map([$this, 'documentRecord'], $statement->fetchAll());
			}
		}
		$statement = $this->pdo->prepare("SELECT d.url, d.title, d.description, d.type, d.keywords, 0 AS rank FROM documents d
			WHERE (d.title LIKE :query OR d.description LIKE :query OR d.keywords LIKE :query OR d.plain_text LIKE :query){$privacy}
			ORDER BY CASE WHEN d.title LIKE :query THEN 0 ELSE 1 END, d.title LIMIT :limit");
		$statement->bindValue(':query', '%' . $query . '%');
		$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
		$statement->execute();
		return array_map([$this, 'documentRecord'], $statement->fetchAll());
	}

	/** @return list<array<string,mixed>> */
	public function records(bool $include_private = false): array
	{
		$this->sync();
		$privacy = $include_private ? '' : "WHERE d.visibility = 'public' AND d.draft = 0";
		$documents = $this->pdo->query("SELECT d.url, d.title, d.description, d.type, d.keywords, d.plain_text AS text FROM documents d {$privacy} ORDER BY d.title")->fetchAll();
		$records = [];
		foreach ($documents as $document) {
			$document['kind'] = 'page';
			$document['page'] = $document['url'];
			$document['keywords'] = array_values(array_filter(explode(',', (string) $document['keywords'])));
			$records[] = $document;
		}
		$sql = "SELECT d.url AS page, d.title AS description, h.anchor, h.title, h.level, d.type, d.keywords
				FROM headings h JOIN documents d ON d.id = h.document_id " . ($include_private ? '' : "WHERE d.visibility = 'public' AND d.draft = 0 ") . 'ORDER BY d.title, h.position';
		foreach ($this->pdo->query($sql)->fetchAll() as $heading) {
			$records[] = [
				'kind' => 'heading', 'page' => $heading['page'], 'url' => $heading['page'] . '#' . $heading['anchor'],
				'title' => $heading['title'], 'description' => $heading['description'], 'text' => $heading['description'] . ' ' . $heading['title'],
				'type' => $heading['type'], 'keywords' => array_values(array_filter(explode(',', (string) $heading['keywords']))),
			];
		}
		return $records;
	}

	public function stats(): array
	{
		$counts = [];
		foreach (['documents', 'headings', 'links', 'keywords', 'document_keywords', 'aliases', 'snippets', 'snippet_usage', 'assets', 'asset_usage', 'settings'] as $table) {
			$counts[$table] = (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
		}
		$counts['last_synced_at'] = (int) ($this->meta('last_synced_at') ?? 0);
		return $counts;
	}

	public function saveStudioState(string $session_id, array $state): void
	{
		$statement = $this->pdo->prepare('INSERT INTO studio_sessions(session_id, state_json, updated_at) VALUES(:id, :state, :time)
			ON CONFLICT(session_id) DO UPDATE SET state_json = excluded.state_json, updated_at = excluded.updated_at');
		$statement->execute(['id' => $session_id, 'state' => json_encode($state, JSON_THROW_ON_ERROR), 'time' => time()]);
	}

	private function indexPage(Page $page): void
	{
		$rendered = $this->renderer->render($page);
		$statement = $this->pdo->prepare('INSERT INTO documents(path,url,title,description,keywords,visibility,draft,nav,type,frontmatter_json,content_hash,plain_text,modified_at,indexed_at)
			VALUES(:path,:url,:title,:description,:keywords,:visibility,:draft,:nav,:type,:frontmatter,:hash,:plain,:modified,:indexed)');
		$statement->execute([
			'path' => $page->relative_path, 'url' => $page->url, 'title' => $page->title, 'description' => $page->description,
			'keywords' => implode(',', $page->keywords()), 'visibility' => $page->isPrivate() ? 'private' : 'public',
			'draft' => (int) $page->isDraft(), 'nav' => (int) $page->isInNavigation(), 'type' => $page->type(),
			'frontmatter' => json_encode($page->meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
			'hash' => hash('sha256', $page->markdown), 'plain' => $rendered->plain_text, 'modified' => $page->modified_at, 'indexed' => time(),
		]);
		$document_id = (int) $this->pdo->lastInsertId();
		$keyword = $this->pdo->prepare('INSERT OR IGNORE INTO keywords(name,slug) VALUES(?,?)');
		$keyword_id = $this->pdo->prepare('SELECT id FROM keywords WHERE name = ? COLLATE NOCASE');
		$attach_keyword = $this->pdo->prepare('INSERT OR IGNORE INTO document_keywords(document_id,keyword_id) VALUES(?,?)');
		foreach ($page->keywords() as $name) {
			$name = trim($name);
			if ($name === '') continue;
			$slug = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)) ?? '', '-');
			if ($slug === '') $slug = substr(hash('sha256', $name), 0, 12);
			$keyword->execute([$name, $slug]);
			$keyword_id->execute([$name]);
			$id = $keyword_id->fetchColumn();
			if ($id === false) {
				$keyword->execute([$name, $slug . '-' . substr(hash('sha256', $name), 0, 7)]);
				$keyword_id->execute([$name]);
				$id = $keyword_id->fetchColumn();
			}
			if ($id !== false) $attach_keyword->execute([$document_id, (int) $id]);
		}
		$alias = $this->pdo->prepare('INSERT INTO aliases(document_id,alias) VALUES(?,?)');
		foreach ($page->aliases() as $path) $alias->execute([$document_id, $path]);
		$heading = $this->pdo->prepare('INSERT INTO headings(document_id,anchor,title,level,position) VALUES(?,?,?,?,?)');
		foreach ($rendered->headings as $position => $item) {
			$heading->execute([$document_id, $item['id'], $item['title'], $item['level'], $position]);
		}
		$link = $this->pdo->prepare('INSERT INTO links(source_document_id,target_url,kind,label) VALUES(?,?,?,?)');
		preg_match_all('/\[([^\]]*)\]\(([^)]+)\)/', $page->markdown, $matches, PREG_SET_ORDER);
		foreach ($matches as $match) {
			$target = trim(explode(' ', $match[2], 2)[0], '<>');
			$kind = str_starts_with($target, '#') ? 'anchor'
				: (preg_match('#^(?:https?:|mailto:)#i', $target) ? 'external'
				: (str_starts_with($target, '/uploads/') ? 'asset' : 'page'));
			$link->execute([$document_id, $target, $kind, $match[1]]);
		}
		$usage = $this->pdo->prepare('INSERT OR IGNORE INTO snippet_usage(snippet_path,document_id) VALUES(?,?)');
		preg_match_all('/:::include\s+path=["\']([^"\']+)["\']/', $page->markdown, $includes);
		foreach ($includes[1] ?? [] as $path) {
			$usage->execute([str_replace('\\', '/', $path), $document_id]);
		}
		$asset_usage = $this->pdo->prepare('INSERT OR IGNORE INTO asset_usage(asset_path,document_id) VALUES(?,?)');
		preg_match_all('#(?:\(|src=["\'])/uploads/([^\s)"\']+)#', $page->markdown, $assets);
		foreach ($assets[1] ?? [] as $path) {
			$asset_usage->execute([rawurldecode($path), $document_id]);
		}
	}

	private function indexSnippets(): void
	{
		$folder = $this->content_dir . '/_snippets';
		$statement = $this->pdo->prepare('INSERT INTO snippets(path,title,content_hash,modified_at) VALUES(?,?,?,?)');
		foreach (glob($folder . '/*.md') ?: [] as $path) {
			$relative = str_replace('\\', '/', substr($path, strlen($this->content_dir) + 1));
			$source = (string) file_get_contents($path);
			$title = ucwords(str_replace(['-', '_'], ' ', pathinfo($path, PATHINFO_FILENAME)));
			$statement->execute([$relative, $title, hash('sha256', $source), (int) filemtime($path)]);
		}
	}

	private function indexAssets(): void
	{
		if (!is_dir($this->upload_dir)) return;
		$statement = $this->pdo->prepare('INSERT INTO assets(path,url,mime,size,modified_at) VALUES(?,?,?,?,?)');
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->upload_dir, FilesystemIterator::SKIP_DOTS));
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		foreach ($iterator as $item) {
			if (!$item->isFile()) continue;
			if (str_starts_with($item->getFilename(), '.')) continue;
			$relative = str_replace('\\', '/', substr($item->getPathname(), strlen($this->upload_dir) + 1));
			$statement->execute([$relative, '/uploads/' . rawurlencode($relative), $finfo->file($item->getPathname()) ?: 'application/octet-stream', $item->getSize(), $item->getMTime()]);
		}
	}

	private function indexSettings(): void
	{
		$statement = $this->pdo->prepare('INSERT INTO settings(key,value_json,source,updated_at) VALUES(?,?,?,?)');
		foreach (['_site.yaml' => 'site', '_theme.yaml' => 'theme'] as $file => $prefix) {
			$path = $this->content_dir . '/' . $file;
			$values = is_file($path) ? (Yaml::parseFile($path) ?? []) : [];
			if (!is_array($values)) continue;
			foreach ($values as $key => $value) {
				$statement->execute([$prefix . '.' . $key, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'yaml', time()]);
			}
		}
	}

	/** @return list<array{name:string,slug:string,usage_count:int}> */
	public function keywords(): array
	{
		$this->sync();
		return $this->pdo->query('SELECT k.name,k.slug,COUNT(dk.document_id) AS usage_count FROM keywords k LEFT JOIN document_keywords dk ON dk.keyword_id=k.id GROUP BY k.id ORDER BY usage_count DESC,k.name')->fetchAll();
	}

	private function fingerprint(): string
	{
		// Path, mtime, and size are enough to detect edits; hashing every file's
		// contents on each sync check made every search pay a full-content read.
		$sources = [];
		foreach ([$this->content_dir, $this->upload_dir] as $folder) {
			if (!is_dir($folder)) continue;
			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));
			foreach ($iterator as $item) {
				if ($item->isFile()) $sources[] = [$item->getPathname(), $item->getMTime(), $item->getSize()];
			}
		}
		sort($sources);
		return hash('sha256', json_encode($sources, JSON_UNESCAPED_SLASHES));
	}

	private function documentRecord(array $row): array
	{
		return [
			'kind' => 'page', 'page' => $row['url'], 'url' => $row['url'], 'title' => $row['title'],
			'description' => $row['description'], 'text' => $row['description'], 'type' => $row['type'],
			'keywords' => array_values(array_filter(explode(',', (string) $row['keywords']))), 'score' => -(float) $row['rank'],
		];
	}

	private function hasFts(): bool
	{
		$statement = $this->pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='documents_fts'");
		return (bool) $statement->fetchColumn();
	}

	private function meta(string $key): ?string
	{
		$statement = $this->pdo->prepare('SELECT value FROM index_meta WHERE key = ?');
		$statement->execute([$key]);
		$value = $statement->fetchColumn();
		return $value === false ? null : (string) $value;
	}

	private function setMeta(string $key, string $value): void
	{
		$statement = $this->pdo->prepare('INSERT INTO index_meta(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value');
		$statement->execute([$key, $value]);
	}
}

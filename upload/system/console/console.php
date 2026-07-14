<?php

declare(strict_types=1);

namespace System\Console;

use RuntimeException;
use Throwable;
use System\Library\Content\ContentRepository;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SearchService;
use System\Library\Content\SiteData;
use System\Library\Content\DirectiveRegistry;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Model\SqliteSearchService;
use System\Library\Service\StaticSiteBuilder;
use System\Engine\Event;
use System\Library\DB;
use System\Library\FileCache;
use System\Library\View;

final class Console
{
	private ContentRepository $repository;
	private SearchService $search;
	private StaticSiteBuilder $builder;

	public function __construct(private readonly array $config)
	{
		$this->repository = new ContentRepository($config['content_dir']);
		$renderer = new MarkdownRenderer((bool) $config['raw_html'], SiteData::load($config['data_file']), $config['content_dir'], new DirectiveRegistry($config['directives'] ?? []));
		$record_builder = new SearchIndexer($this->repository, $renderer, $config['cache_dir'] . '/search-index.json');
		$database = new DB($config['database_path']);
		$events = new Event();
		(new Schema($database, $events))->migrate();
		$index = new ContentIndex($database, $events, $this->repository, $renderer, $config['content_dir'], $config['upload_dir']);
		$this->search = new SqliteSearchService($database, $events, $index, $record_builder);
		$this->builder = new StaticSiteBuilder($config, $this->repository, $renderer, $this->search, new View(dirname(__DIR__, 2) . '/frontend/view/template'));
	}

	public function run(array $arguments): int
	{
		$command = $arguments[1] ?? 'help';
		try {
			return match ($command) {
				'validate' => $this->validate(),
				'index' => $this->index(),
				'cache:clear' => $this->clear(),
				'doctor' => $this->doctor(),
				'version' => $this->version(),
				'build' => $this->build($arguments),
				default => $this->help(),
			};
		} catch (Throwable $exception) {
			fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
			return 1;
		}
	}

	private function validate(): int
	{
		$errors = $this->builder->validate();
		if ($errors) {
			fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
			return 1;
		}
		echo 'Validated ' . $this->builder->pageCount() . ' pages.' . PHP_EOL;
		return 0;
	}

	private function index(): int
	{
		$documents = $this->search->build();
		echo 'Built ' . count($documents) . ' search records.' . PHP_EOL;
		return 0;
	}

	private function clear(): int
	{
		(new FileCache($this->config['cache_dir']))->clear();
		echo 'Cache cleared.' . PHP_EOL;
		return 0;
	}

	private function doctor(): int
	{
		$checks = [];
		$checks['PHP 8.4 or newer'] = PHP_VERSION_ID >= 80400;
		foreach (['dom', 'json', 'mbstring', 'pdo', 'pdo_sqlite'] as $extension) {
			$checks['PHP extension ' . $extension] = extension_loaded($extension);
		}
		$checks['Site root is readable'] = is_dir($this->config['site_root']) && is_readable($this->config['site_root']);
		$checks['Canonical content is readable'] = is_dir($this->config['content_dir']) && is_readable($this->config['content_dir']);
		foreach (['cache_dir', 'revision_dir', 'export_dir', 'upload_dir'] as $key) {
			$path = $this->config[$key];
			if (!is_dir($path)) @mkdir($path, 0775, true);
			$checks['Writable ' . basename($path)] = is_dir($path) && is_writable($path);
		}
		if ($this->config['editor_enabled']) {
			$checks['Writable canonical content'] = is_writable($this->config['content_dir']);
			$checks['Environment configuration'] = !is_file($this->config['environment_file']) || is_readable($this->config['environment_file']);
		}
		try {
			(new DB($this->config['database_path']))->connection()->query('SELECT 1')->fetchColumn();
			$checks['SQLite database'] = true;
		} catch (Throwable) {
			$checks['SQLite database'] = false;
		}
		$checks['Documentation pages'] = $this->repository->all(true, true) !== [];
		$failed = false;
		foreach ($checks as $label => $passed) {
			echo ($passed ? '[OK]   ' : '[FAIL] ') . $label . PHP_EOL;
			$failed = $failed || !$passed;
		}
		echo '[INFO] ZIP browser exports: ' . (class_exists(\ZipArchive::class) ? 'available' : 'unavailable (CLI exports still work)') . PHP_EOL;
		echo '[INFO] Version: ' . ($this->config['version'] ?? 'development') . PHP_EOL;
		echo '[INFO] Site root: ' . $this->config['site_root'] . PHP_EOL;
		echo '[INFO] State root: ' . $this->config['state_root'] . PHP_EOL;
		return $failed ? 1 : 0;
	}

	private function version(): int
	{
		echo ($this->config['version'] ?? 'development') . PHP_EOL;
		return 0;
	}

	private function build(array $arguments): int
	{
		$destination = dirname(__DIR__, 2) . '/build';
		$profile = 'public';
		$acknowledge_secrets = false;
		foreach (array_slice($arguments, 2) as $argument) {
			if (str_starts_with($argument, '--profile=')) {
				$profile = substr($argument, 10);
			} elseif ($argument === '--acknowledge-secrets') {
				$acknowledge_secrets = true;
			} elseif (!str_starts_with($argument, '--')) {
				$destination = $argument;
			} else {
				throw new RuntimeException('Unknown build option: ' . $argument);
			}
		}
		$built = $this->builder->build($destination, $profile, $acknowledge_secrets);
		echo 'Built ' . $profile . ' static site in ' . (realpath($built) ?: $built) . PHP_EOL;
		return 0;
	}

	private function help(): int
	{
		echo "Lightdocs\n\n  doctor         Verify this PHP/LXC deployment\n  version        Print the installed version\n  validate       Validate content and links\n  index          Rebuild the SQLite search index\n  cache:clear    Clear rendered caches\n  build [dir] [--profile=public|private|sanitized] [--acknowledge-secrets]\n                 Export a static site\n";
		return 0;
	}
}

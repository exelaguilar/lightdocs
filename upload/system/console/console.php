<?php

declare(strict_types=1);

namespace System\Console;

use RuntimeException;
use LogicException;
use Throwable;
use System\Library\Content\ContentRepository;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\SearchIndexer;
use System\Library\Content\SearchService;
use System\Library\Content\SiteData;
use System\Library\Content\DirectiveRegistry;
use System\Library\Content\Glossary;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Model\SqliteSearchService;
use System\Library\Service\StaticSiteBuilder;
use System\Engine\Config;
use System\Engine\Event;
use System\Engine\Registry;
use System\Library\DB;
use System\Library\FileCache;
use System\Library\Template;
use System\Library\AssetPublisher;
use System\Library\Job\JobHandlerInterface;
use System\Library\JobQueue;
use System\Library\JobWorker;
use System\Library\Schedule;
use System\Library\Service\CssBuilder;

final class Console
{
	/** @var array<string, mixed> */
	private array $config;
	private ContentRepository $repository;
	private SearchService $search;
	private StaticSiteBuilder $builder;
	private Registry $registry;
	private DB $database;
	private JobQueue $jobQueue;

	public function __construct(Registry $registry)
	{
		$this->registry = $registry;
		$config = $registry->get('config');
		if (!$config instanceof Config) {
			throw new LogicException('Console requires a booted Registry with Config.');
		}
		$this->config = $config->all();

		$database = new DB($config->get('database_path'));
		$registry->set('db', $database);
		$this->database = $database;

		$events = new Event($registry);
		$registry->set('event', $events);

		(new Schema($registry))->migrate();
		$this->jobQueue = new JobQueue($database);
		$registry->set('job_queue', $this->jobQueue);

		$assetPublisher = new AssetPublisher(
			(string)$config->get('asset_public_root'),
			(string)$config->get('asset_public_base'),
			(bool)$config->get('asset_read_only', false),
			2,
			(string)$config->get('asset_state_root'),
		);
		$registry->set('asset_publisher', $assetPublisher);
		$assetManifest = $assetPublisher->manifest();
		$config->set('published_assets', $assetManifest['assets'] ?? []);
		$registry->set('css', new CssBuilder($config->all(), $assetPublisher));

		$this->repository = new ContentRepository($config->get('content_dir'));
		$registry->set('repository', $this->repository);

		$renderer = new MarkdownRenderer((bool) $config->get('raw_html'), SiteData::load($config->get('data_file')), $config->get('content_dir'), new DirectiveRegistry((array) $config->get('directives')), new Glossary($config->get('glossary_file')));
		$registry->set('renderer', $renderer);

		$registry->set('json_search', new SearchIndexer($this->repository, $renderer, $config->get('cache_dir') . '/search-index.json'));

		$index = new ContentIndex($registry);
		$registry->set('index', $index);

		$this->search = new SqliteSearchService($registry);
		$registry->set('search', $this->search);

		$template = new Template($config->get('template_engine', 'template'), $config);
		$template->addPath(dirname(__DIR__, 2) . '/frontend/view/template/');

		$this->builder = new StaticSiteBuilder($this->config, $this->repository, $renderer, $this->search, $template, $events);
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
				'jobs:run' => $this->runJobs($arguments),
				default => $this->help(),
			};
		} catch (Throwable $exception) {
			fwrite(STDERR, 'Error: ' . $exception->getMessage() . PHP_EOL);
			return 1;
		}
	}

	private function runJobs(array $arguments): int
	{
		$schedules = (array)($this->config['job_schedules'] ?? []);
		$materialized = (new Schedule($this->jobQueue, $this->database))->materialize($schedules);
		$handlers = [];

		foreach ((array)($this->config['job_handlers'] ?? []) as $type => $class) {
			if (!is_string($class) || !class_exists($class)) {
				throw new RuntimeException('Invalid job handler class for ' . $type . '.');
			}

			$handler = new $class($this->registry);
			if (!$handler instanceof JobHandlerInterface) {
				throw new RuntimeException($class . ' must implement JobHandlerInterface.');
			}
			$handlers[(string)$type] = $handler;
		}

		$limit = isset($arguments[2]) ? max(0, (int)$arguments[2]) : 20;
		$summary = $handlers === []
			? ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'retried' => 0, 'recovered' => $this->jobQueue->recoverStaleLeases()]
			: (new JobWorker($this->jobQueue, $handlers))->run($limit, array_keys($handlers));

		echo json_encode(['materialized' => $materialized] + $summary, JSON_THROW_ON_ERROR) . PHP_EOL;
		return $summary['failed'] > 0 ? 1 : 0;
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
		echo "Lightdocs\n\n  doctor         Verify this PHP/LXC deployment\n  version        Print the installed version\n  validate       Validate content and links\n  index          Rebuild the SQLite search index\n  cache:clear    Clear rendered caches\n  jobs:run [n]   Materialize schedules and run a bounded job batch\n  build [dir] [--profile=public|private|sanitized] [--acknowledge-secrets]\n                 Export a static site\n";
		return 0;
	}
}

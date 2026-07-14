<?php

declare(strict_types=1);

use System\Library\Content\ContentRepository;
use System\Library\Content\MarkdownRenderer;
use System\Library\Content\Page;
use System\Library\Content\SiteData;
use System\Model\ContentIndex;
use System\Model\Schema;
use System\Library\Service\SiteSettings;
use System\Library\Service\GitHistory;
use System\Library\Service\GitSyncPreflight;
use System\Library\Service\SecretRedactor;
use System\Library\DB;
use System\Engine\Event;
use System\Engine\ExtensionManager;
use System\Engine\Startup;
use System\Engine\Model;
use System\Library\Content\DirectiveRegistry;

$config = require dirname(__DIR__) . '/upload/system/startup.php';
$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$repository = new ContentRepository($config['content_dir']);
$renderer = new MarkdownRenderer((bool) $config['raw_html'], SiteData::load($config['data_file']), $config['content_dir'], $config['directives']);
$events = new Event();
$eventPayload = null;
$events->listen('smoke.event', static function (mixed $payload) use (&$eventPayload): void { $eventPayload = $payload; });
$events->dispatch('smoke.event', ['ok' => true]);
$check(($eventPayload['ok'] ?? false) === true, 'Synchronous system events did not dispatch payloads.');
$check(is_subclass_of(ContentIndex::class, Model::class), 'ContentIndex does not extend the system Model base.');
$check(!is_subclass_of(SiteSettings::class, Model::class), 'SiteSettings should write canonical files without extending the SQLite Model base.');
$mainDB = new DB($config['database_path']);
(new Schema($mainDB, $events))->migrate();
$directives = new DirectiveRegistry($config['directives']);
$extensions = new ExtensionManager($config, $mainDB, $repository, $directives, new Startup());
$check(in_array('local_git', $extensions->names(), true), 'The Local Git extension did not register with the system extension manager.');
$check($extensions->get('local_git.history') instanceof GitHistory, 'The Local Git extension did not register its history service.');
$index = new ContentIndex($mainDB, $events, $repository, $renderer, $config['content_dir'], $config['upload_dir']);
$rebuiltPayload = null;
$events->listen('index.rebuilt', static function (mixed $payload) use (&$rebuiltPayload): void { $rebuiltPayload = $payload; });
$stats = $index->sync(true);
$check(is_array($rebuiltPayload) && ($rebuiltPayload['documents'] ?? -1) === $stats['documents'], 'index.rebuilt did not fire after the committed rebuild with index statistics.');

$publicPages = $repository->all();
$allPages = $repository->all(true, true);
$check($publicPages !== [], 'No public pages were discovered.');
$check(count($allPages) >= count($publicPages), 'Private/draft filtering is inconsistent.');
$check($stats['documents'] === count($allPages), 'SQLite document count does not match canonical Markdown.');
$check($stats['headings'] > 0, 'SQLite heading index is empty.');
$check(array_key_exists('keywords', $stats), 'SQLite keyword taxonomy is unavailable.');
$check(($stats['settings'] ?? 0) >= 4, 'SQLite site settings mirror is incomplete.');
$check($index->search('deployment') !== [], 'SQLite search returned no results for a known term.');
$runbook = new Page('', 'runbook.md', '/runbook', 'Runbook', '', "- [ ] Verify the deployment.\n", ['type' => 'runbook'], time());
$check(str_contains($renderer->render($runbook)->html, 'type="checkbox"'), 'Runbook task lists did not render interactive progress inputs.');
$check((new GitHistory($config['site_root'], false))->inspect()['state'] === 'disabled', 'Optional Git history did not remain disabled by default.');
$check(in_array((new GitHistory($config['site_root'], true))->inspect()['state'], ['ready', 'empty', 'not_repository', 'unavailable'], true), 'Optional Git history did not degrade safely.');

$redaction = (new SecretRedactor())->redact("contains_secrets: true\r\nPASSWORD=keep-me-private\r\ncommand --token ghp_abcdefghijklmnopqrstuvwxyz123456\r\nname: safe\r\n");
$check($redaction['replacements'] === 2, 'Shared secret redaction did not recognize assignment and command credentials.');
$check(!str_contains($redaction['contents'], 'keep-me-private') && str_contains($redaction['contents'], '"<redacted>"'), 'Shared secret redaction leaked a recognized value or produced an unsafe placeholder.');
$check(str_contains($redaction['contents'], 'contains_secrets: true'), 'Secret redaction changed harmless visibility metadata.');
$preflightRoot = $config['cache_dir'] . '/preflight-smoke-' . bin2hex(random_bytes(3));
mkdir($preflightRoot, 0775, true);
file_put_contents($preflightRoot . '/public.md', "---\ntitle: Public\n---\n\nSafe page.\n");
file_put_contents($preflightRoot . '/private.md', "---\ntitle: Private\nvisibility: private\ncontains_secrets: true\n---\n\nPASSWORD=private-fixture-value\n");
$preflightRepository = new ContentRepository($preflightRoot);
$preflight = (new GitSyncPreflight($preflightRoot, $preflightRepository))->inspect('sanitized');
$check($preflight['replacements'] > 0, 'Git preflight did not find known private-source credentials.');
$publicPreflight = (new GitSyncPreflight($preflightRoot, $preflightRepository))->inspect('public');
$check($publicPreflight['excluded'] > 0, 'Public-only Git preflight did not exclude private or draft pages.');
@unlink($preflightRoot . '/public.md');
@unlink($preflightRoot . '/private.md');
@rmdir($preflightRoot);

$escapeRenderer = new MarkdownRenderer(false, ['services' => ['demo' => ['id' => '103']]], $config['content_dir'], $config['directives']);
$escapePage = new Page('', 'escape.md', '/escape', 'Escape', '', "Escaped: \\{{ services.demo.id }}\n\nResolved: {{ services.demo.id }}", [], time());
$html = $escapeRenderer->render($escapePage)->html;
$check(str_contains($html, 'Escaped: {{ services.demo.id }}'), 'Escaped template variable was not preserved literally.');
$check(str_contains($html, 'Resolved: 103'), 'Unescaped template variable was not resolved.');

$settingsRoot = $config['cache_dir'] . '/settings-smoke-' . bin2hex(random_bytes(3));
$envPath = $settingsRoot . '/.env';
if (!is_dir($settingsRoot)) mkdir($settingsRoot, 0775, true);
file_put_contents($envPath, "DOCS_ADMIN_PASSWORD=keep-this-exactly\nDOCS_ACCENT=#000000\n");
$settings = new SiteSettings($settingsEvents = new Event(), $settingsRoot . '/_site.yaml', $settingsRoot . '/_theme.yaml', $envPath);
$settingsSavedPayload = null;
$settingsEvents->listen('settings.saved', static function (mixed $payload) use (&$settingsSavedPayload): void { $settingsSavedPayload = $payload; });
$settings->save(['name' => 'Smoke Docs', 'tagline' => 'Portable settings', 'base_url' => '', 'github_url' => 'https://github.com/example/docs', 'accent' => '#7c3aed', 'radius' => 'medium', 'density' => 'comfortable', 'content_width' => 'normal']);
$check(($settingsSavedPayload['site']['name'] ?? '') === 'Smoke Docs', 'settings.saved did not fire with the persisted values.');
$savedSettings = $settings->read();
$check(($savedSettings['site']['name'] ?? '') === 'Smoke Docs', 'Portable site settings could not be written.');
$check(($savedSettings['theme']['default_theme'] ?? '') === 'system', 'Default color scheme was not persisted.');
$check(($savedSettings['site']['github_url'] ?? '') === 'https://github.com/example/docs', 'Repository link was not persisted.');
$check(!array_key_exists('git_history', $savedSettings['site']), 'Local Git preference still belongs to global site settings.');
$savedEnv = (string) file_get_contents($envPath);
$check(str_contains($savedEnv, 'DOCS_ACCENT=#7c3aed'), 'Studio settings did not update safe environment overrides.');
$check(str_contains($savedEnv, 'DOCS_ADMIN_PASSWORD=keep-this-exactly'), 'Studio settings changed an unrelated credential.');
foreach (glob($settingsRoot . '/*') ?: [] as $file) @unlink($file);
@unlink($envPath);
@rmdir($settingsRoot);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Lightdocs smoke tests passed: ' . count($publicPages) . ' public pages, ' . $stats['documents'] . ' indexed documents, ' . $stats['headings'] . ' headings.' . PHP_EOL;

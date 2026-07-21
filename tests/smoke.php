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
use System\Library\ExtensionState;
use System\Engine\Event;
use System\Engine\ExtensionAdministration;
use System\Engine\ExtensionApplication;
use System\Engine\ExtensionCapabilityRegistry;
use System\Engine\ExtensionDiscovery;
use System\Engine\ExtensionManager;
use System\Engine\ExtensionManifest;
use System\Engine\Startup;
use System\Engine\Model;
use System\Library\Content\DirectiveRegistry;
use System\Library\ExtensionPackageInstaller;
use System\Library\Content\Glossary;
use System\Library\Content\NavigationManager;
use System\Library\User;

define('APP_CONTEXT', 'frontend');
require dirname(__DIR__) . '/upload/system/startup.php';

$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);
$autoloader->register('Admin', DIR_ROOT . 'admin/');
$autoloader->register('Frontend', DIR_ROOT . 'frontend/');
$autoloader->register('Extension', DIR_ROOT . 'extension/');

use System\Engine\Config;
use System\Engine\Registry;
use System\Engine\CallbackAction;

$registry = new Registry();
$configuration = new Config();
$configuration->load('default.php');
$configuration->load('frontend.php');
$registry->set('config', $configuration);
$config = $configuration->all();

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$buildExtensions = static function (array $config, DB $database, ContentRepository $repository, DirectiveRegistry $directives, \System\Engine\Autoloader $autoloader): ExtensionAdministration {
    $state = new ExtensionState($database);
    $startups = new Startup();
    $capabilities = new ExtensionCapabilityRegistry();
    $capabilities->register('lightdocs.application', static fn (ExtensionManifest $manifest): ExtensionApplication => new ExtensionApplication(
        $manifest->name(), $config, $repository, $directives, $database, $state->settings($manifest->name()), $startups
    ));
    $manager = new ExtensionManager(
        new ExtensionDiscovery($config['extension_dir']),
        $state,
        capabilities: $capabilities,
        platformVersions: ['php' => PHP_VERSION, 'tinymvc' => '0.11.0'],
        autoloader: $autoloader,
        packages: new ExtensionPackageInstaller($config['extension_dir']),
    );
    $runtime = $manager->boot('public');
    return new ExtensionAdministration($manager, $runtime, $state, $startups);
};

$repository = new ContentRepository($config['content_dir']);
$renderer = new MarkdownRenderer((bool) $config['raw_html'], SiteData::load($config['data_file']), $config['content_dir'], $config['directives'], new Glossary($config['glossary_file']));
$events = new Event($registry);
$eventPayload = null;
$events->register('smoke.event', new CallbackAction(static function (mixed $payload) use (&$eventPayload): void { $eventPayload = $payload; }, 'smoke.listener'));
$smokePayload = ['ok' => true];
$smokeArgs = [&$smokePayload];
$events->trigger('smoke.event', $smokeArgs);
$check(($eventPayload['ok'] ?? false) === true, 'Synchronous system events did not dispatch payloads.');
$check(is_subclass_of(ContentIndex::class, Model::class), 'ContentIndex does not extend the system Model base.');
$check(!is_subclass_of(SiteSettings::class, Model::class), 'SiteSettings should write canonical files without extending the SQLite Model base.');
$mainDB = new DB($config['database_path']);
$registry->set('db', $mainDB);
$registry->set('event', $events);
$registry->set('repository', $repository);
$registry->set('renderer', $renderer);
(new Schema($registry))->migrate();
$directives = new DirectiveRegistry($config['directives']);
$extensions = $buildExtensions($config, $mainDB, $repository, $directives, $autoloader);
$check(in_array('local_git', $extensions->names(), true), 'The Local Git extension did not register with the system extension manager.');
$check($extensions->get('local_git.history') instanceof GitHistory, 'The Local Git extension did not register its history service.');
$extensionRows = $extensions->all();
$check(($extensionRows['reader_banner']['type'] ?? '') === 'example', 'The Reader Banner extension type was not discovered.');
$check(($extensions->settingsFor('reader_banner')['contexts'] ?? []) === ['public'], 'The Reader Banner public-reader context was not discovered.');
$extensionRoot = $config['cache_dir'] . '/extension-smoke-' . bin2hex(random_bytes(3));
$extensionDb = new DB($extensionRoot . '/lightdocs.sqlite');
$extensionRegistry = new Registry();
$extensionRegistry->set('config', $configuration);
$extensionRegistry->set('db', $extensionDb);
(new Schema($extensionRegistry))->migrate();
$bannerExtensions = $buildExtensions($config, $extensionDb, $repository, new DirectiveRegistry($config['directives']), $autoloader);
$bannerExtensions->setExtensionEnabled('reader_banner', true);
$bannerExtensions->setSettings('reader_banner', ['message' => 'Smoke banner', 'accent_color' => '#7c3aed', 'icon' => 'check', 'location' => 'above_content', 'page_scope' => 'all', 'dismissible' => false]);
$bannerExtensions = $buildExtensions($config, $extensionDb, $repository, new DirectiveRegistry($config['directives']), $autoloader);
$bannerEvents = new Event($extensionRegistry);
$bannerExtensions->registerEvents($bannerEvents);
$bannerAssets = $bannerExtensions->assets();
$check(!in_array('/extension/reader_banner/view/stylesheet/reader_banner.css', $bannerAssets['public']['styles'], true), 'The Reader Banner extension still registers its removed component stylesheet.');
$check(in_array('/extension/reader_banner/view/javascript/reader_banner.js', $bannerAssets['public']['scripts'], true), 'The Reader Banner extension did not register its public JavaScript.');
$bannerPayload = ['page' => new Page('', 'guides/example.md', '/guides/example', 'Example', '', '', [], time()), 'content' => '<article>Example</article>', 'private_access' => false];
$bannerArgs = [&$bannerPayload];
$bannerEvents->trigger('frontend/page/content/after', $bannerArgs);
$check(str_contains($bannerPayload['content'], 'data-reader-banner'), 'The Reader Banner extension did not modify public page content through the reader hook.');
$check(str_starts_with($bannerPayload['content'], '<aside class="my-4 flex'), 'The Reader Banner location setting did not place the banner before page content.');
$check(str_contains($bannerPayload['content'], '--reader-banner-accent:#7c3aed') && str_contains($bannerPayload['content'], 'stroke-[var(--reader-banner-accent,var(--brand))]'), 'The Reader Banner visual settings were not applied.');
$check(!str_contains($bannerPayload['content'], 'data-reader-banner-dismiss'), 'The Reader Banner dismissible setting was not applied.');
unset($bannerExtensions, $extensionDb);
@unlink($extensionRoot . '/lightdocs.sqlite');
@unlink($extensionRoot . '/lightdocs.sqlite-shm');
@unlink($extensionRoot . '/lightdocs.sqlite-wal');
@rmdir($extensionRoot);
$index = new ContentIndex($registry);
$rebuiltPayload = null;
$events->register('index.rebuilt', new CallbackAction(static function (mixed $payload) use (&$rebuiltPayload): void { $rebuiltPayload = $payload; }, 'smoke.rebuilt'));
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
$glossaryPage = new Page('', 'glossary.md', '/glossary', 'Glossary', '', "Use [Proxmox VE](/glossary#proxmox-ve).\n\n[[proxmox-ve]]", [], time());
$glossaryHtml = $renderer->render($glossaryPage)->html;
$check(str_contains($glossaryHtml, 'href="/glossary#proxmox-ve"') && str_contains($glossaryHtml, 'data-glossary-term="proxmox-ve"'), 'Standard glossary links did not render as progressive popovers.');
$check(str_contains($glossaryHtml, '[[proxmox-ve]]'), 'Legacy glossary syntax should remain plain text rather than being transformed.');
$componentPage = new Page('', 'components.md', '/components', 'Components', '', ":::type-table title=\"Options\"\n| Name | Type |\n| --- | --- |\n| id | integer |\n:::\n\n:::repo-card title=\"Lightdocs\" url=\"https://github.com/exelaguilar/lightdocs\" branch=\"main\"\nRepository details.\n:::\n\n:::output title=\"Response\" open\nReady.\n:::\n\n:::graph title=\"Explore\"\n:::\n", [], time());
$componentHtml = $renderer->render($componentPage)->html;
$check(str_contains($componentHtml, 'Type reference') || str_contains($componentHtml, 'Options'), 'Type table directives did not render.');
$check(str_contains($componentHtml, 'https://github.com/exelaguilar/lightdocs') && str_contains($componentHtml, '>git<'), 'Repository card directives did not render.');
$check(str_contains($componentHtml, '<details') && str_contains($componentHtml, 'Response'), 'Output directives did not render.');
$check(str_contains($componentHtml, 'href="/graph"'), 'Graph directives did not render.');
$glossaryRoot = $config['cache_dir'] . '/glossary-smoke-' . bin2hex(random_bytes(3));
mkdir($glossaryRoot, 0775, true);
$editableGlossary = new Glossary($glossaryRoot . '/_glossary.yaml');
$editableGlossary->save('snapshot', 'Snapshot', 'A point-in-time copy of data.', ['backup']);
$check(($editableGlossary->find('snapshot')['aliases'] ?? []) === ['backup'], 'Glossary terms could not be saved with editor aliases.');
$editableGlossary->delete('snapshot');
$check($editableGlossary->find('snapshot') === null, 'Glossary terms could not be removed.');
@unlink($glossaryRoot . '/_glossary.yaml');
@rmdir($glossaryRoot);
$scheduled = new Page('', 'scheduled.md', '/scheduled', 'Scheduled', '', '', ['status' => 'published', 'publish_at' => gmdate(DATE_ATOM, time() + 3600)], time());
$check($scheduled->isScheduled() && $scheduled->isDraft(), 'Scheduled publishing did not keep a future page out of public routing.');
$review = new Page('', 'review.md', '/review', 'Review', '', '', ['status' => 'review'], time());
$check($review->isDraft() && $review->status() === 'review', 'Review status did not remain non-public.');
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
$settings = new SiteSettings($settingsEvents = new Event(new Registry()), $settingsRoot . '/_site.yaml', $settingsRoot . '/_theme.yaml', $envPath);
$settingsSavedPayload = null;
$settingsEvents->register('settings.saved', new CallbackAction(static function (mixed $payload) use (&$settingsSavedPayload): void { $settingsSavedPayload = $payload; }, 'smoke.settings'));
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

$navigationRoot = $config['cache_dir'] . '/navigation-smoke-' . bin2hex(random_bytes(3));
mkdir($navigationRoot . '/guides', 0775, true);
$navigation = new NavigationManager($navigationRoot);
$navigation->saveSections([['path' => 'guides', 'title' => 'Guides', 'description' => 'Smoke section', 'icon' => 'book', 'order' => 10]]);
$navigation->saveFolder(['path' => 'guides', 'title' => 'Guides', 'description' => 'Smoke folder', 'icon' => 'book', 'order' => 10, 'collapsed' => true]);
$check(($navigation->sections()[0]['path'] ?? '') === 'guides', 'Navigation section settings could not be persisted.');
$check(($navigation->folders()[0]['collapsed'] ?? false) === true, 'Navigation folder metadata could not be persisted.');
@unlink($navigationRoot . '/_sections.yaml');
@unlink($navigationRoot . '/guides/_meta.yaml');
@rmdir($navigationRoot . '/guides');
@rmdir($navigationRoot);

$accountsRoot = $config['cache_dir'] . '/accounts-smoke-' . bin2hex(random_bytes(3));
$accountsDb = new DB($accountsRoot . '/lightdocs.sqlite');
$accountsConfig = new Config();
$accountsConfig->load('default.php');
$accountsConfig->set('admin_password', 'SmokePassword-123');
$accountsRegistry = new Registry();
$accountsRegistry->set('config', $accountsConfig);
$accountsRegistry->set('db', $accountsDb);
(new Schema($accountsRegistry))->migrate();
$accounts = new \Admin\Model\Common\User($accountsRegistry);
$account = $accounts->login('admin', 'SmokePassword-123', '127.0.0.1');
$check($account !== null, 'Seeded administrator could not authenticate.');
$check($account !== null && (int) ($account['user_group_id'] ?? 0) === 1, 'Seeded administrator does not belong to the super admin group.');
$check($account !== null && $accounts->isProtectedAdminUser($account), 'Bootstrap administrator protection did not remain application-owned.');
$check($accounts->isProtectedUserGroupId(1), 'Bootstrap administrator group is not protected.');
$check(!$accounts->isProtectedAdminUser(['user_id' => 99, 'user_group_id' => 2, 'is_protected' => 0]), 'Ordinary editor account was incorrectly protected.');
$smokeGroup = $accounts->getGroup(2);
$check(in_array('editor/editor', $smokeGroup['permission']['access'] ?? [], true), 'Seeded editor group did not receive route-based access permissions.');
if ($account !== null) {
	$accounts->registerSession((int) $account['user_id'], 'smoke-session', '127.0.0.1', 'Smoke test');
	$sessions = array_column($accounts->getSessions((int) $account['user_id']), 'session_id');
	$check(in_array('smoke-session', $sessions, true), 'Tracked administrator session was not registered.');
	$accounts->revokeOtherSessions('different-session', (int) $account['user_id']);
	$sessions = array_column($accounts->getSessions((int) $account['user_id']), 'session_id');
	$check(!in_array('smoke-session', $sessions, true), 'Administrator session revocation did not take effect.');
}
unset($accounts, $accountsDb);
@unlink($accountsRoot . '/lightdocs.sqlite');
@unlink($accountsRoot . '/lightdocs.sqlite-shm');
@unlink($accountsRoot . '/lightdocs.sqlite-wal');
@rmdir($accountsRoot);

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo 'Lightdocs smoke tests passed: ' . count($publicPages) . ' public pages, ' . $stats['documents'] . ' indexed documents, ' . $stats['headings'] . ' headings.' . PHP_EOL;

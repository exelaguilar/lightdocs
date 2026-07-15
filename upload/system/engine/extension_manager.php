<?php

declare(strict_types=1);

namespace System\Engine;

use RuntimeException;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\DB;
use System\Library\ExtensionState;
use ZipArchive;

final class ExtensionManager
{
	/** @var array<string,array<string,mixed>> */
	private array $manifests = [];

	/** @var array<string,ExtensionInterface> */
	private array $loaded = [];

	/** @var array<string,mixed> */
	private array $services = [];

	/** @var array<string,list<array{listener:callable,extension:string,event:string,enabled:bool}>> */
	private array $listeners = [];

	/** @var array<string,list<string>> */
	private array $extension_services = [];

	/** @var array<string,list<string>> */
	private array $extension_events = [];

	/** @var array<string,list<array<string,mixed>>> */
	private array $navigation = [];

	private string $registering = '';
	private readonly string $directory;

	private ?DirectiveRegistry $current_directives = null;

	private ExtensionState $state;

	public function __construct(private readonly array $config, DB $database, ContentRepository $repository, DirectiveRegistry $directives, private readonly Startup $startups)
	{
		$this->state = new ExtensionState($database);
		$this->current_directives = $directives;
		$this->directory = rtrim((string) ($config['extension_dir'] ?? DIR_ROOT . 'extension'), '/\\');
		$this->discover($this->directory);

		foreach ($this->manifests as $name => $manifest) {
			$this->state->syncExtension($name, (string) ($manifest['version'] ?? ''), (bool) ($manifest['default_enabled'] ?? false));
			foreach (($manifest['events'] ?? []) as $event) {
				if (!is_array($event) || empty($event['code']) || empty($event['event'])) continue;
				$this->state->syncEvent((string) $event['code'], $name, (string) $event['event'], (int) ($event['sort_order'] ?? 0), (string) ($event['description'] ?? ''));
			}
			foreach (($manifest['settings'] ?? []) as $setting) {
				if (is_array($setting) && !empty($setting['key'])) $this->state->syncSetting($name, (string) $setting['key'], $setting['default'] ?? '');
			}
		}

		foreach ($this->manifests as $name => $manifest) {
			if (!$this->state->isExtensionEnabled($name)) continue;
			$class = (string) ($manifest['class'] ?? '');
			$this->loadClass($name, $class);
			if ($class === '' || !class_exists($class)) throw new RuntimeException('Extension class is unavailable: ' . $name);
			$extension = new $class(new ExtensionContext($config, $repository, $directives, $database, $this->state->settings($name)));
			if (!$extension instanceof ExtensionInterface) throw new RuntimeException('Extension class is invalid: ' . $name);
			$this->add($extension);
		}
	}

	private function discover(string $directory): void
	{
		if (!is_dir($directory)) return;
		foreach (scandir($directory) ?: [] as $name) {
			if ($name === '.' || $name === '..' || !is_dir($directory . '/' . $name)) continue;
			$path = $directory . '/' . $name . '/extension.json';
			if (!is_file($path)) continue;
			$manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($manifest) || !preg_match('/^[a-z0-9_]+$/', (string) ($manifest['name'] ?? ''))) continue;
			$this->manifests[(string) $manifest['name']] = $manifest;
		}
	}

	private function loadClass(string $name, string $class): void
	{
		if ($class === '' || class_exists($class, false)) return;
		$path = $this->directory . '/' . $name . '/extension.php';
		if (is_file($path)) require_once $path;
	}

	public function add(ExtensionInterface $extension): self
	{
		$name = $extension->name();
		if (!isset($this->manifests[$name])) throw new RuntimeException('Extension manifest is unavailable: ' . $name);
		if (isset($this->loaded[$name])) throw new RuntimeException('Extension already loaded: ' . $name);
		$this->loaded[$name] = $extension;
		$this->registering = $name;
		try {
			$extension->register($this);
			foreach (($this->manifests[$name]['navigation'] ?? []) as $item) {
				if (is_array($item)) $this->navigation($item);
			}
		} finally {
			$this->registering = '';
		}
		return $this;
	}

	public function service(string $name, mixed $service): self
	{
		$this->services[$name] = $service;
		[$extension] = explode('.', $name, 2);
		$this->extension_services[$extension][] = $name;
		return $this;
	}

	public function require(string $name): mixed
	{
		if (!array_key_exists($name, $this->services)) throw new RuntimeException('Extension service is not registered: ' . $name);
		return $this->services[$name];
	}

	public function get(string $name): mixed
	{
		return $this->services[$name] ?? null;
	}

	public function on(string $event, callable $listener, ?string $code = null): self
	{
		$extension = $this->registering;
		$code ??= $extension . '.' . str_replace(['/', ' '], ['.', '_'], $event);
		$this->state->syncEvent($code, $extension, $event);
		$this->extension_events[$extension][] = $code;
		$this->listeners[$event][] = ['listener' => $listener, 'extension' => $extension, 'event' => $event, 'enabled' => $this->state->isEventEnabled($code)];
		return $this;
	}

	public function startup(string $name, callable $callback, int $sort_order = 0): self
	{
		if ($this->registering === '') throw new RuntimeException('Startups can only be registered by an extension.');
		$this->startups->register($this->registering . '.' . $name, $callback, $sort_order);
		return $this;
	}

	public function runStartups(Event $events): void
	{
		$this->startups->run($events, $this);
	}

	public function navigation(array $item): self
	{
		$item['extension'] = $this->registering;
		$this->navigation[$item['section'] ?? 'Extensions'][] = $item;
		return $this;
	}

	public function directive(string $name, callable $handler): self
	{
		$this->current_directives?->register($name, $handler);
		return $this;
	}

	public function registerEvents(Event $events): void
	{
		foreach ($this->listeners as $event => $listeners) {
			foreach ($listeners as $listener) {
				if ($listener['enabled']) $events->listen($event, $listener['listener'], $listener['extension'] . '.' . $event);
			}
		}
	}

	public function setExtensionEnabled(string $name, bool $enabled): void
	{
		if (!isset($this->manifests[$name])) throw new RuntimeException('Unknown extension: ' . $name);
		$this->state->setExtensionEnabled($name, $enabled);
	}

	public function setEventEnabled(string $code, bool $enabled): void
	{
		$this->state->setEventEnabled($code, $enabled);
	}

	public function setSettings(string $name, array $input): void
	{
		if (!isset($this->manifests[$name])) throw new RuntimeException('Unknown extension: ' . $name);
		$current = $this->state->settings($name);
		foreach (($this->manifests[$name]['settings'] ?? []) as $definition) {
			if (!is_array($definition) || empty($definition['key'])) continue;
			$key = (string) $definition['key'];
			$value = $input[$key] ?? $current[$key] ?? ($definition['default'] ?? '');
			if (($definition['type'] ?? 'text') === 'password' && trim((string) $value) === '') $value = $current[$key] ?? ($definition['default'] ?? '');
			if (($definition['type'] ?? 'text') === 'number') $value = max((int) ($definition['min'] ?? 0), min((int) ($definition['max'] ?? PHP_INT_MAX), (int) $value));
			if (($definition['type'] ?? 'text') === 'boolean') $value = (bool) $value;
			$this->state->setSetting($name, $key, $value);
		}
	}

	public function defineEvent(string $name, string $description): void
	{
		$name = trim($name);
		if (!preg_match('/^[a-z][a-z0-9_.-]{2,80}$/', $name)) throw new RuntimeException('Event names must use lowercase letters, numbers, dots, dashes, or underscores.');
		$this->state->defineEvent('custom.' . $name, $name, trim($description));
	}

	/** @return list<string> */
	public function names(): array
	{
		return array_keys($this->loaded);
	}

	public function all(): array
	{
		$rows = [];
		foreach ($this->manifests as $name => $manifest) {
			$rows[$name] = [
				'name' => $name,
				'version' => (string) ($manifest['version'] ?? ''),
				'description' => (string) ($manifest['description'] ?? ''),
				'class' => (string) ($manifest['class'] ?? ''),
				'enabled' => $this->state->isExtensionEnabled($name),
				'loaded' => isset($this->loaded[$name]),
				'removable' => is_file($this->directory . '/' . $name . '/.lightdocs-installed'),
				'services' => $this->extension_services[$name] ?? [],
				'events' => $this->extension_events[$name] ?? array_values(array_map(static fn (array $event): string => (string) $event['code'], array_filter(($manifest['events'] ?? []), 'is_array'))),
			];
		}
		return $rows;
	}

	public function events(): array
	{
		$events = $this->state->events();
		foreach ($events as &$event) {
			$event['extension_enabled'] = $event['extension'] === 'core' || $this->state->isExtensionEnabled((string) $event['extension']);
			$event['enabled'] = (bool) $event['enabled'] && $event['extension_enabled'];
			$event['loaded'] = isset($this->listeners[$event['event']]);
		}
		return $events;
	}

	public function settings(): array
	{
		$settings = [];
		foreach ($this->manifests as $name => $manifest) {
			$definitions = array_values(array_filter(($manifest['settings'] ?? []), 'is_array'));
			if ($definitions === []) continue;
			$settings[] = ['name' => $name, 'enabled' => $this->state->isExtensionEnabled($name), 'definitions' => $definitions, 'values' => $this->state->settings($name)];
		}
		return $settings;
	}

	public function settingsFor(string $name): ?array
	{
		foreach ($this->settings() as $setting) if ($setting['name'] === $name) return $setting;
		return null;
	}

	public function navigationItems(): array
	{
		$items = [];
		foreach ($this->navigation as $section => $entries) {
			usort($entries, static fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
			$items[$section] = $entries;
		}
		return $items;
	}

	public function install(array $upload): string
	{
		if (!class_exists(ZipArchive::class)) throw new RuntimeException('ZIP support is unavailable.');
		if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) throw new RuntimeException('Choose a valid extension ZIP archive.');
		$temporary = rtrim((string) ($this->config['state_root'] ?? sys_get_temp_dir()), '/\\') . '/extension-install-' . bin2hex(random_bytes(8));
		if (!mkdir($temporary, 0700, true) && !is_dir($temporary)) throw new RuntimeException('Could not prepare the extension installer.');
		$zip = new ZipArchive();
		if ($zip->open((string) $upload['tmp_name']) !== true) throw new RuntimeException('The extension archive could not be opened.');
		try {
			for ($index = 0; $index < $zip->numFiles; $index++) {
				$name = (string) $zip->getNameIndex($index);
				if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $name)) throw new RuntimeException('The extension contains an unsafe path.');
			}
			if (!$zip->extractTo($temporary)) throw new RuntimeException('The extension archive could not be extracted.');
		} finally { $zip->close(); }
		$manifest_path = is_file($temporary . '/extension.json') ? $temporary . '/extension.json' : (($matches = glob($temporary . '/*/extension.json') ?: []) ? $matches[0] : '');
		if ($manifest_path === '') throw new RuntimeException('The extension archive must contain extension.json.');
		$manifest = json_decode((string) file_get_contents($manifest_path), true);
		$name = is_array($manifest) ? (string) ($manifest['name'] ?? '') : '';
		$class = is_array($manifest) ? (string) ($manifest['class'] ?? '') : '';
		if (!preg_match('/^[a-z0-9_]+$/', $name) || $class === '') throw new RuntimeException('The extension manifest is invalid.');
		$source = dirname($manifest_path);
		$target = $this->directory . '/' . $name;
		if (is_dir($target) && !is_file($target . '/.lightdocs-installed')) throw new RuntimeException('A bundled extension with that name cannot be replaced.');
		if (is_dir($target)) $this->removeDirectory($target);
		if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) throw new RuntimeException('The extension directory is not writable.');
		if (!rename($source, $target)) throw new RuntimeException('The extension could not be installed.');
		file_put_contents($target . '/.lightdocs-installed', date(DATE_ATOM), LOCK_EX);
		$this->removeDirectory($temporary);
		return $name;
	}

	public function remove(string $name): void
	{
		if (!preg_match('/^[a-z0-9_]+$/', $name)) throw new RuntimeException('Invalid extension name.');
		$target = $this->directory . '/' . $name;
		if (!is_file($target . '/.lightdocs-installed')) throw new RuntimeException('Bundled extensions cannot be removed from the admin UI.');
		$this->removeDirectory($target);
	}

	private function removeDirectory(string $directory): void
	{
		if (!is_dir($directory)) return;
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $file) $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
		@rmdir($directory);
	}
}

<?php

declare(strict_types=1);

namespace System\Engine;

use RuntimeException;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\DB;
use System\Library\ExtensionState;

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

	private ?DirectiveRegistry $current_directives = null;

	private ExtensionState $state;

	public function __construct(array $config, DB $database, ContentRepository $repository, DirectiveRegistry $directives, private readonly Startup $startups)
	{
		$this->state = new ExtensionState($database);
		$this->current_directives = $directives;
		$directory = rtrim((string) ($config['extension_dir'] ?? DIR_ROOT . 'extension'), '/\\');
		$this->discover($directory);

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
}

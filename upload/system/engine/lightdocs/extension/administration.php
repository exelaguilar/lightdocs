<?php

declare(strict_types=1);

namespace System\Engine\Lightdocs\Extension;

use System\Engine\Startup;
use System\Engine\Event;
use System\Engine\CallbackAction;
use System\Engine\Extension as ExtensionRuntime;
use RuntimeException;
use System\Library\ExtensionState;

final class Administration
{
	/** @var array<string,array<string,mixed>> */
	private array $manifests = [];

	/** @var array<string,list<string>> */
	private array $extensionEvents = [];

	/** @var array<string,list<array<string,mixed>>> */
	private array $navigation = [];

	/** @var array<string,array{styles:list<string>,scripts:list<string>}> */
	private array $assets = [
		'admin' => ['styles' => [], 'scripts' => []],
		'public' => ['styles' => [], 'scripts' => []],
	];

	private Manager $manager;
	private ExtensionRuntime $runtime;
	private ExtensionState $state;
	private Startup $startups;

	public function __construct(Manager $manager, ExtensionRuntime $runtime, ExtensionState $state, Startup $startups)
	{
		$this->manager = $manager;
		$this->runtime = $runtime;
		$this->state = $state;
		$this->startups = $startups;

		foreach ($manager->catalog() as $name => $manifest) {
			$data = $manifest->all();
			$this->manifests[$name] = $data;
			foreach (($data['events'] ?? []) as $event) {
				if (!is_array($event) || empty($event['code']) || empty($event['event'])) continue;
				$this->state->syncEvent((string) $event['code'], $name, (string) $event['event'], (int) ($event['sort_order'] ?? 0), (string) ($event['description'] ?? ''));
			}
			foreach (($data['settings'] ?? []) as $setting) {
				if (is_array($setting) && !empty($setting['key'])) $this->state->syncSetting($name, (string) $setting['key'], $setting['default'] ?? '');
			}
		}

		foreach ($runtime->listeners() as $listener) {
			$this->state->syncEvent($listener['code'], $listener['extension'], $listener['event']);
			$this->extensionEvents[$listener['extension']][] = $listener['code'];
		}

		foreach ($runtime->manifests() as $name => $manifest) {
			foreach (($manifest->all()['navigation'] ?? []) as $item) {
				if (!is_array($item)) continue;
				$item['extension'] = $name;
				$this->navigation[$item['section'] ?? 'Extensions'][] = $item;
			}
		}

		foreach ($runtime->assetContributions() as $asset) {
			if (!isset($this->assets[$asset['context']])) continue;
			$bucket = $asset['type'] === 'style' ? 'styles' : 'scripts';
			$path = '/extension/' . $asset['extension'] . '/' . ltrim($asset['path'], '/');
			$this->assets[$asset['context']][$bucket][] = $path;
			$this->assets[$asset['context']][$bucket] = array_values(array_unique($this->assets[$asset['context']][$bucket]));
		}
	}

	public function require(string $name): mixed
	{
		return $this->runtime->require($name);
	}

	public function get(string $name): mixed
	{
		return $this->runtime->get($name);
	}

	/** @return array<string,mixed> */
	public function services(): array
	{
		return $this->runtime->services();
	}

	public function runStartups(Event $events): void
	{
		$this->startups->run($events, $this);
	}

	/** @return array{admin:array{styles:list<string>,scripts:list<string>},public:array{styles:list<string>,scripts:list<string>}} */
	public function assets(): array
	{
		return $this->assets;
	}

	public function registerEvents(Event $events): void
	{
		foreach ($this->runtime->listeners() as $listener) {
			if (!$this->state->isEventEnabled($listener['code'])) continue;
			$callback = $listener['listener'];
			$eventName = $listener['event'];
			$events->register($eventName, new CallbackAction(static function (&$payload) use ($callback, $eventName): mixed {
				return $callback($payload, $eventName);
			}, $listener['code']), (int)($listener['priority'] ?? 0), $listener['code'], 'extension.' . $listener['extension']);
		}
	}

	public function setExtensionEnabled(string $name, bool $enabled): void
	{
		if (!isset($this->manifests[$name])) throw new RuntimeException('Unknown extension: ' . $name);
		$this->manager->setEnabled($name, $enabled);
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
			$type = (string) ($definition['type'] ?? 'text');
			$value = $input[$key] ?? $current[$key] ?? ($definition['default'] ?? '');
			if ($type === 'password' && trim((string) $value) === '') $value = $current[$key] ?? ($definition['default'] ?? '');
			if ($type === 'number') $value = max((int) ($definition['min'] ?? 0), min((int) ($definition['max'] ?? PHP_INT_MAX), (int) $value));
			if ($type === 'boolean') $value = (bool) $value;
			if ($type === 'color' && !preg_match('/^#[a-f0-9]{6}$/i', (string) $value)) $value = $definition['default'] ?? '#000000';
			if ($type === 'select') {
				$options = is_array($definition['options'] ?? null) ? $definition['options'] : [];
				$values = array_values(array_filter(array_map(static fn (mixed $option): string => is_array($option) ? (string) ($option['value'] ?? '') : (string) $option, $options)));
				if (!in_array((string) $value, $values, true)) $value = $definition['default'] ?? ($values[0] ?? '');
			}
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
		return $this->runtime->names();
	}

	public function all(): array
	{
		$rows = [];
		$installations = $this->manager->installations();
		$loaded = array_flip($this->runtime->names());
		foreach ($this->manifests as $name => $manifest) {
			$installation = $installations[$name] ?? null;
			$contexts = is_array($manifest['contexts'] ?? null) ? $manifest['contexts'] : [];
			$rows[$name] = [
				'name' => $name,
				'version' => (string) ($manifest['version'] ?? ''),
				'description' => (string) ($manifest['description'] ?? ''),
				'type' => $this->type($manifest),
				'contexts' => array_values(array_intersect($contexts, ['admin', 'public'])),
				'class' => (string) ($manifest['class'] ?? ''),
				'enabled' => $installation?->enabled() ?? false,
				'loaded' => isset($loaded[$name]),
				'removable' => $installation?->source() === 'uploaded',
				'status' => $installation?->status() ?? 'unknown',
				'error' => $installation?->error(),
				'services' => array_values(array_filter(array_keys($this->runtime->services()), static fn (string $service): bool => str_starts_with($service, $name . '.'))),
				'events' => $this->extensionEvents[$name] ?? array_values(array_map(static fn (array $event): string => (string) $event['code'], array_filter(($manifest['events'] ?? []), 'is_array'))),
			];
		}
		return $rows;
	}

	public function events(): array
	{
		$events = $this->state->events();
		$loadedCodes = array_flip(array_column($this->runtime->listeners(), 'code'));
		$installations = $this->manager->installations();
		foreach ($events as &$event) {
			$installation = $installations[(string) $event['extension']] ?? null;
			$event['extension_enabled'] = $event['extension'] === 'core' || ($installation?->enabled() ?? false);
			$event['enabled'] = (bool) $event['enabled'] && $event['extension_enabled'];
			$event['loaded'] = isset($loadedCodes[(string) $event['code']]);
		}
		return $events;
	}

	public function settings(): array
	{
		$settings = [];
		$installations = $this->manager->installations();
		foreach ($this->manifests as $name => $manifest) {
			$definitions = array_values(array_filter(($manifest['settings'] ?? []), 'is_array'));
			if ($definitions === []) continue;
			$notes = is_array($manifest['settings_notes'] ?? null) ? $manifest['settings_notes'] : [];
			$installation = $installations[$name] ?? null;
			$settings[] = [
				'name' => $name,
				'version' => (string) ($manifest['version'] ?? ''),
				'description' => (string) ($manifest['description'] ?? ''),
				'type' => $this->type($manifest),
				'contexts' => array_values(array_intersect(is_array($manifest['contexts'] ?? null) ? $manifest['contexts'] : [], ['admin', 'public'])),
				'enabled' => $installation?->enabled() ?? false,
				'definitions' => $definitions,
				'values' => $this->state->settings($name),
				'settings_summary' => (string) ($manifest['settings_summary'] ?? ''),
				'settings_notes' => array_values(array_filter($notes, 'is_string')),
			];
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
		if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) throw new RuntimeException('Choose a valid extension ZIP archive.');
		return $this->manager->installArchive((string) $upload['tmp_name'])->name();
	}

	public function remove(string $name): void
	{
		$this->manager->uninstall($name);
	}

	private function type(array $manifest): string
	{
		$type = (string) ($manifest['type'] ?? 'utility');
		return preg_match('/^[a-z][a-z0-9_-]{1,30}$/', $type) ? $type : 'utility';
	}
}

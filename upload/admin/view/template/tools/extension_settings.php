<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$extension_name = (string) $extension_setting['name'];
$extension_label = ucwords(str_replace('_', ' ', $extension_name));
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?= $e($extension_label) ?> settings - <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard extension-settings-page">
	<header class="page-header">
		<div><span class="panel-eyebrow">Extensions</span><h1><?= $e($extension_label) ?></h1><p>Configure this extension independently from the core application.</p></div>
		<div class="page-header-actions"><span class="status-pill <?= $extension_setting['enabled'] ? '' : 'is-disabled' ?>"><?= $extension_setting['enabled'] ? 'Enabled' : 'Disabled' ?></span><a class="button secondary-button" href="/admin/extensions">Back to extensions</a></div>
	</header>
	<div class="management-layout extension-settings-layout">
		<section class="dashboard-panel extension-settings-card">
			<header><div><p class="panel-eyebrow">Configuration</p><h2>Extension settings</h2><p>Save credentials and preferences here. They remain separate from site settings.</p></div></header>
			<form method="post" class="extension-settings-form">
				<input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
				<div class="extension-settings-grid">
				<?php foreach ($extension_setting['definitions'] as $definition): ?>
					<?php $setting_key = (string) $definition['key']; $setting_type = (string) ($definition['type'] ?? 'text'); $setting_value = $extension_setting['values'][$setting_key] ?? ($definition['default'] ?? ''); ?>
					<?php if ($setting_type === 'boolean'): ?><label class="extension-setting-toggle"><input type="hidden" name="settings[<?= $e($setting_key) ?>]" value="0"><input type="checkbox" name="settings[<?= $e($setting_key) ?>]" value="1" <?= $setting_value ? 'checked' : '' ?>><span><strong><?= $e($definition['label'] ?? ucwords(str_replace('_', ' ', $setting_key))) ?></strong><?php if (!empty($definition['description'])): ?><small><?= $e($definition['description']) ?></small><?php endif; ?></span></label><?php else: ?><label class="extension-setting-field"><span><?= $e($definition['label'] ?? ucwords(str_replace('_', ' ', $setting_key))) ?></span><input type="<?= in_array($setting_type, ['number', 'password', 'url'], true) ? $setting_type : 'text' ?>" name="settings[<?= $e($setting_key) ?>]" value="<?= $setting_type === 'password' ? '' : $e((string) $setting_value) ?>" <?= isset($definition['min']) ? 'min="' . (int) $definition['min'] . '"' : '' ?> <?= isset($definition['max']) ? 'max="' . (int) $definition['max'] . '"' : '' ?>><?php if (!empty($definition['description'])): ?><small><?= $e($definition['description']) ?></small><?php endif; ?></label><?php endif; ?>
				<?php endforeach; ?>
				</div>
				<footer class="extension-settings-actions"><button class="button" type="submit">Save settings</button><a class="button secondary-button" href="/admin/extensions">Cancel</a></footer>
			</form>
		</section>
		<aside class="management-intro extension-settings-aside">
			<div><p class="panel-eyebrow">Extension state</p><h2><?= $e($extension_label) ?></h2></div>
			<p><?= $extension_setting['enabled'] ? 'This extension is loaded on the next request and may contribute services, events, or navigation.' : 'This extension is currently disabled. Settings can still be saved now, then the extension can be enabled from the Extensions page.' ?></p>
			<p>Disable an extension to remove its runtime services and event listeners without deleting its saved configuration.</p>
			<a class="table-link" href="/admin/extensions">Manage extension state</a>
		</aside>
	</div>
</main>
</body>
</html>

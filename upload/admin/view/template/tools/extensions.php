<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Extensions · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header">
		<div>
			<span class="panel-eyebrow">System</span>
			<h1>Extensions</h1>
			<p>Small, focused packages that add services or behavior to the core application.</p>
		</div>
	</header>
	<div class="management-layout">
		<section class="dashboard-panel management-panel">
			<header>
				<div><p class="panel-eyebrow">Runtime</p><h2>Loaded extensions</h2></div>
				<span class="status-pill"><?= count($extensions) ?> extension<?= count($extensions) === 1 ? '' : 's' ?></span>
			</header>
			<?php if (!$extensions): ?>
				<div class="table-empty">No extensions are loaded. Extensions are added by the application deployment.</div>
			<?php else: ?>
				<section class="table-toolbar" aria-label="Extension table controls"><p><strong><?= count($extensions) ?> extensions</strong><span>Installed in this deployment</span></p><input class="table-search" type="search" placeholder="Filter extensions" data-table-filter="extensions"></section>
				<div class="admin-table-wrap"><table class="admin-table" data-table="extensions"><thead><tr><th>Extension</th><th>Status</th><th>Version</th><th>Services</th><th class="table-actions">Actions</th></tr></thead><tbody>
				<?php foreach ($extensions as $extension): ?><tr><td><strong><?= $e($extension['name']) ?></strong><small><?= $e($extension['description']) ?></small></td><td><span class="status-pill <?= $extension['enabled'] ? '' : 'is-disabled' ?>"><?= $extension['enabled'] ? ($extension['loaded'] ? 'Enabled' : 'Unavailable') : 'Disabled' ?></span></td><td>v<?= $e($extension['version']) ?></td><td><?= count($extension['services']) ?></td><td class="table-actions"><div class="table-action-group"><?php if (in_array($extension['name'], array_column($extension_settings, 'name'), true)): ?><a class="table-link" href="/admin/extensions/<?= rawurlencode($extension['name']) ?>/settings">Configure</a><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="extension" value="<?= $e($extension['name']) ?>"><input type="hidden" name="enabled" value="<?= $extension['enabled'] ? '0' : '1' ?>"><button type="submit" class="text-button"><?= $extension['enabled'] ? 'Disable' : 'Enable' ?></button></form></div></td></tr><?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</section>
		<aside class="management-intro">
			<div><p class="panel-eyebrow">How extensions work</p><h2>Optional capability, shared core</h2></div>
			<p>An extension is a small PHP class that is loaded during bootstrap. It can register a named service and listen for application events without changing the MVC engine.</p>
			<ol>
				<li>The framework loads the extension.</li>
				<li>The extension registers services or listeners.</li>
				<li>Controllers request services when they need them.</li>
			</ol>
			<p class="management-note">Extension code is installed with the release, but activation is managed here. Disabling an extension removes its services, events, and navigation on the next request.</p>
		</aside>
	</div>
</main>
</body>
</html>

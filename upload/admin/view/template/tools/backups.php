<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Backups · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Tools</span><h1>Backups</h1><p>Protect editable documentation source, uploaded assets, and local revisions with a private recovery archive.</p></div><a class="button secondary-button" href="/admin/extensions/backup/settings">Backup settings</a></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<div class="management-layout">
		<section class="dashboard-panel management-panel backup-panel">
			<header><div><p class="panel-eyebrow">Create archive</p><h2>New backup</h2><p>Archives contain editable Markdown, site settings, uploaded assets, and optionally local revisions.</p></div></header>
			<form method="post" class="backup-create-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Backup label</span><input name="label" value="manual" maxlength="40" required></label><button class="button" type="submit">Create full backup</button></form>
		</section>
		<aside class="management-intro"><div><p class="panel-eyebrow">Backup versus export</p><h2>Recovery, not publishing</h2></div><p>Backups preserve editable source and runtime assets for recovery. <a href="/admin/export">Exports</a> build publishable static bundles for deployment or sharing, using public, sanitized, or private profiles.</p><p>Archives stay outside the public web root and are removed after download. Configure retention, revisions, and uploads from <a href="/admin/extensions/backup/settings">Backup settings</a>.</p></aside>
	</div>
		<section class="dashboard-panel management-panel backup-list-panel"><header><div><p class="panel-eyebrow">Available archives</p><h2><?= count($archives) ?> backup<?= count($archives) === 1 ? '' : 's' ?></h2><p>Full backups include the SQLite account, extension, event, and audit state when enabled.</p></div></header>
		<?php if (!$archives): ?><div class="table-empty">No backups have been created yet.</div><?php else: ?><div class="admin-table-wrap table-borderless"><table class="admin-table"><thead><tr><th>Archive</th><th>Created</th><th>Includes</th><th>Size</th><th class="table-actions">Actions</th></tr></thead><tbody><?php foreach ($archives as $archive): ?><tr><td><strong><?= $e($archive['file']) ?></strong></td><td><?= $e(date('Y-m-d H:i:s', $archive['created_at'])) ?></td><td><small><?= !empty($archive['includes']['database']) ? 'Database' : 'Content only' ?><?= !empty($archive['includes']['uploads']) ? ' · Assets' : '' ?><?= !empty($archive['includes']['revisions']) ? ' · Revisions' : '' ?></small></td><td><?= $e(number_format($archive['size'])) ?> bytes</td><td class="table-actions"><div class="table-action-group"><a class="table-link" href="/admin/backups/download?file=<?= rawurlencode($archive['file']) ?>">Download</a><form method="post" action="/admin/backups/restore" data-confirm="Restore this backup? The current database will be preserved beside the restored copy."><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="file" value="<?= $e($archive['file']) ?>"><button class="text-button" type="submit">Restore</button></form></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
	</section>
</main>
</body>
</html>

<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Developer tools · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Tools</span><h1>Developer tools</h1><p>Safe maintenance actions for development and deployment troubleshooting.</p></div></header>
	<?php if ($message): ?><div class="form-success" role="status"><?= $e($message) ?></div><?php endif; ?>
	<div class="developer-grid">
		<section class="dashboard-panel developer-card"><div><span class="developer-icon">CACHE</span><h2>Clear application cache</h2><p>Removes rendered page and search cache files. The next request rebuilds what it needs.</p></div><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button" name="action" value="clear_cache">Clear cache</button></form></section>
		<section class="dashboard-panel developer-card"><div><span class="developer-icon">INDEX</span><h2>Rebuild content index</h2><p>Scans canonical Markdown, snippets, assets, links, and settings into SQLite again.</p></div><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button" name="action" value="rebuild_index">Rebuild index</button></form></section>
		<section class="dashboard-panel developer-card developer-card-danger"><div><span class="developer-icon">SESSION</span><h2>Reset admin session</h2><p>Signs out this browser session and returns to the administrator login page.</p></div><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><button class="button button-danger" name="action" value="reset_session">Sign out</button></form></section>
	</div>
	<p class="management-note developer-warning">These actions affect this installation only. They do not delete canonical Markdown, uploaded assets, revisions, extensions, or local Git history.</p>
	<?php if ($audit_available): ?><section class="dashboard-panel developer-card"><div><span class="developer-icon">AUDIT</span><h2>Audit log</h2><p>Review, sort, and filter framework activity captured by the Audit extension.</p></div><a class="button secondary-button" href="/admin/audit">Open audit log</a></section><?php endif; ?>
</main>
</body>
</html>

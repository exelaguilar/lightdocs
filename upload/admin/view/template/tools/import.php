<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Import Markdown · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard import-page">
	<header class="page-header"><div><span class="panel-eyebrow">Content</span><h1>Import Markdown</h1><p>Bring a folder of Markdown notes into canonical content without shell access.</p></div><a class="button secondary-button" href="/admin/editor">Open editor</a></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<div class="management-layout">
		<section class="dashboard-panel import-panel"><header><div><p class="panel-eyebrow">Import archive</p><h2>Markdown ZIP</h2><p>Files retain their folder structure. A top-level <code>content/</code> directory is optional.</p></div></header><form method="post" enctype="multipart/form-data" class="import-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Archive</span><input type="file" name="archive" accept=".zip,application/zip" required><small>Only Markdown files are imported. Each file is validated before it is written.</small></label><label class="extension-setting-toggle"><input type="hidden" name="overwrite" value="0"><input type="checkbox" name="overwrite" value="1"><span><strong>Replace existing files</strong><small>Leave this off to safely skip paths that already exist.</small></span></label><footer><button class="button" type="submit">Import content</button><a class="button secondary-button" href="/admin/editor">Cancel</a></footer></form></section>
		<aside class="management-intro"><div><p class="panel-eyebrow">Safe migration</p><h2>What this does</h2></div><p>The importer accepts Markdown only, prevents unsafe paths, validates frontmatter, and rebuilds the content index after a successful import.</p><p>It never deletes existing files. Enable replacement only when you intend to update matching paths. Use a full backup before a large migration.</p><a class="table-link" href="/admin/backups">Open backups</a></aside>
	</div>
</main>
</body>
</html>

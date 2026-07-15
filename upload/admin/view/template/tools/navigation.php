<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Navigation · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard navigation-page">
	<header class="page-header"><div><span class="panel-eyebrow">Content</span><h1>Navigation</h1><p>Organize sections and folder labels without editing YAML by hand.</p></div></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<form method="post">
		<input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
		<section class="dashboard-panel navigation-panel"><header><div><p class="panel-eyebrow">Top-level sections</p><h2>Section switcher</h2><p>These entries are stored in <code>content/_sections.yaml</code>.</p></div><button class="button secondary-button" type="button" data-navigation-add-section>Add section</button></header><div class="navigation-list" data-navigation-sections>
		<?php foreach ($sections as $index => $section): ?><div class="navigation-row"><label><span>Path</span><input name="sections[<?= (int) $index ?>][path]" value="<?= $e($section['path']) ?>" required></label><label><span>Title</span><input name="sections[<?= (int) $index ?>][title]" value="<?= $e($section['title'] ?? '') ?>"></label><label><span>Description</span><input name="sections[<?= (int) $index ?>][description]" value="<?= $e($section['description'] ?? '') ?>"></label><label><span>Icon</span><input name="sections[<?= (int) $index ?>][icon]" value="<?= $e($section['icon'] ?? 'folder') ?>"></label><label><span>Order</span><input type="number" name="sections[<?= (int) $index ?>][order]" value="<?= (int) ($section['order'] ?? 100) ?>"></label></div><?php endforeach; ?>
		<?php if (!$sections): ?><div class="table-empty" data-navigation-empty>No sections configured. The documentation tree still works from folders.</div><?php endif; ?>
		</div></section>
		<section class="dashboard-panel navigation-panel"><header><div><p class="panel-eyebrow">Folder labels</p><h2>Content folders</h2><p>These entries are stored beside each folder in <code>_meta.yaml</code>.</p></div></header><div class="navigation-list">
		<?php foreach ($folders as $index => $folder): ?><div class="navigation-row"><input type="hidden" name="folders[<?= (int) $index ?>][path]" value="<?= $e($folder['path']) ?>"><label><span>Folder</span><input value="<?= $e($folder['path']) ?>" disabled></label><label><span>Title</span><input name="folders[<?= (int) $index ?>][title]" value="<?= $e($folder['title']) ?>"></label><label><span>Description</span><input name="folders[<?= (int) $index ?>][description]" value="<?= $e($folder['description']) ?>"></label><label><span>Icon</span><input name="folders[<?= (int) $index ?>][icon]" value="<?= $e($folder['icon']) ?>"></label><label><span>Order</span><input type="number" name="folders[<?= (int) $index ?>][order]" value="<?= (int) $folder['order'] ?>"></label><label class="extension-setting-toggle"><input type="hidden" name="folders[<?= (int) $index ?>][collapsed]" value="0"><input type="checkbox" name="folders[<?= (int) $index ?>][collapsed]" value="1" <?= $folder['collapsed'] ? 'checked' : '' ?>><span><strong>Collapsed</strong><small>Start this folder closed.</small></span></label></div><?php endforeach; ?>
		<?php if (!$folders): ?><div class="table-empty">No content folders have custom metadata.</div><?php endif; ?>
		</div></section>
		<footer class="page-header-actions navigation-actions"><button class="button" type="submit">Save navigation</button><a class="button secondary-button" href="/admin/editor">Open editor</a></footer>
	</form>
</main>
<script>
document.querySelector('[data-navigation-add-section]')?.addEventListener('click', () => {
	const list = document.querySelector('[data-navigation-sections]');
	const index = list.querySelectorAll('.navigation-row').length;
	document.querySelector('[data-navigation-empty]')?.remove();
	const row = document.createElement('div');
	row.className = 'navigation-row';
	row.innerHTML = '<label><span>Path</span><input name="sections[' + index + '][path]" required></label><label><span>Title</span><input name="sections[' + index + '][title]"></label><label><span>Description</span><input name="sections[' + index + '][description]"></label><label><span>Icon</span><input name="sections[' + index + '][icon]" value="folder"></label><label><span>Order</span><input type="number" name="sections[' + index + '][order]" value="100"></label>';
	list.append(row);
	row.querySelector('input')?.focus();
});
</script>
</body>
</html>

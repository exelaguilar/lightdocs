<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Media library · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard media-page">
	<header class="page-header">
		<div><span class="panel-eyebrow">Content</span><h1>Media library</h1><p>Browse uploaded assets, see where they are used, and keep the site tidy.</p></div>
		<form method="post" enctype="multipart/form-data" class="page-header-actions media-upload-form" data-media-upload-form><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="upload"><label class="button secondary-button media-upload-button"><span data-media-upload-label>Select media</span><input type="file" name="asset" accept="image/*,.pdf,.txt" required data-media-upload-file></label><small data-media-upload-status>Selecting a file uploads it immediately.</small></form>
	</header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<section class="dashboard-panel management-panel">
		<header><div><p class="panel-eyebrow">Library</p><h2><?= count($assets) ?> asset<?= count($assets) === 1 ? '' : 's' ?></h2></div><span class="status-pill">Persistent uploads</span></header>
		<section class="table-toolbar" aria-label="Media table controls"><p><strong>Uploaded files</strong><span>Assets remain outside the application release.</span></p><input class="table-search" type="search" placeholder="Filter media" data-table-filter="media"></section>
		<?php if (!$assets): ?><div class="table-empty">No media has been uploaded yet. Add an image or document to begin.</div><?php else: ?>
		<div class="admin-table-wrap"><table class="admin-table media-table" data-table="media"><thead><tr><th>File</th><th>Dimensions</th><th>Size</th><th>Usage</th><th class="table-actions">Actions</th></tr></thead><tbody>
		<?php foreach ($assets as $asset): ?><tr><td><strong class="media-name"><?php if ($asset['width'] !== null): ?><img src="<?= $e($asset['url']) ?>" alt="" loading="lazy"><?php else: ?><span class="media-file-icon" aria-hidden="true">FILE</span><?php endif; ?><span><?= $e($asset['name']) ?></span></strong><small><?= $e($asset['url']) ?></small></td><td><?= $asset['width'] !== null ? (int) $asset['width'] . ' × ' . (int) $asset['height'] . ' px' : '—' ?></td><td><?= $e(number_format($asset['size'] / 1024, 1)) ?> KB</td><td><?= count($asset['usages']) ?> page<?= count($asset['usages']) === 1 ? '' : 's' ?></td><td class="table-actions"><div class="table-action-group"><button class="table-action-button" type="button" data-media-rename="<?= $e($asset['name']) ?>">Rename</button><button class="table-link" type="button" data-media-preview="<?= $e($asset['url']) ?>" data-media-preview-name="<?= $e($asset['name']) ?>" data-media-preview-image="<?= $asset['width'] !== null ? '1' : '0' ?>">Open</button><form method="post" data-confirm="Delete this asset? Pages that reference it will show a missing asset."><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="name" value="<?= $e($asset['name']) ?>"><button class="text-button" type="submit">Delete</button></form></div></td></tr><?php endforeach; ?>
		</tbody></table></div>
		<?php endif; ?>
	</section>
</main>
<dialog class="media-rename-dialog" data-media-dialog><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="rename"><input type="hidden" name="name" data-media-original><label><span>New filename</span><input name="new_name" data-media-new required></label><footer><button class="button secondary-button" type="button" data-media-cancel>Cancel</button><button class="button" type="submit">Rename asset</button></footer></form></dialog>
<dialog class="media-preview-dialog" data-media-preview-dialog><header><strong data-media-preview-title>Preview</strong><button type="button" data-media-preview-close aria-label="Close preview">×</button></header><div data-media-preview-content></div></dialog>
<script defer src="/admin/view/javascript/admin.js?v=<?= @filemtime(dirname(__DIR__, 3) . '/view/javascript/admin.js') ?: 1 ?>"></script>
<script>
document.querySelectorAll('[data-media-rename]').forEach((button) => button.addEventListener('click', () => {
	const dialog = document.querySelector('[data-media-dialog]');
	dialog.querySelector('[data-media-original]').value = button.dataset.mediaRename;
	dialog.querySelector('[data-media-new]').value = button.dataset.mediaRename;
	dialog.showModal();
}));
document.querySelector('[data-media-cancel]')?.addEventListener('click', () => document.querySelector('[data-media-dialog]')?.close());
const mediaPreviewDialog = document.querySelector('[data-media-preview-dialog]');
document.querySelectorAll('[data-media-preview]').forEach((button) => button.addEventListener('click', () => {
	const content = mediaPreviewDialog.querySelector('[data-media-preview-content]');
	const preview = document.createElement(button.dataset.mediaPreviewImage === '1' ? 'img' : 'iframe');
	preview.src = button.dataset.mediaPreview;
	if (preview.tagName === 'IMG') preview.alt = button.dataset.mediaPreviewName;
	else preview.title = button.dataset.mediaPreviewName;
	content.replaceChildren(preview);
	mediaPreviewDialog.querySelector('[data-media-preview-title]').textContent = button.dataset.mediaPreviewName;
	mediaPreviewDialog.showModal();
}));
document.querySelector('[data-media-preview-close]')?.addEventListener('click', () => mediaPreviewDialog?.close());
mediaPreviewDialog?.addEventListener('click', (event) => { if (event.target === mediaPreviewDialog) mediaPreviewDialog.close(); });
const mediaUploadFile = document.querySelector('[data-media-upload-file]');
mediaUploadFile?.addEventListener('change', () => {
	if (!mediaUploadFile.files?.length) return;
	document.querySelector('[data-media-upload-label]').textContent = 'Uploading…';
	document.querySelector('[data-media-upload-status]').textContent = mediaUploadFile.files[0].name;
	mediaUploadFile.closest('form')?.requestSubmit();
});
</script>
</body>
</html>

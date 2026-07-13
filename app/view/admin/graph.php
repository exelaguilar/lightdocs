<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Content Map &middot; <?= $e($config['name']) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 1 ?>"><style>:root{--brand:<?= $e($config['accent']) ?>}</style></head>
<body class="editor-body">
<?php require __DIR__ . '/_header.php'; ?>
<main class="graph-shell">
  <header class="health-header"><div><p class="panel-eyebrow">Content intelligence</p><h1>Documentation relationships</h1><p>See which pages are connected and which are isolated.</p></div><div class="graph-summary"><strong><?= count($relationships) ?></strong><span>Published pages</span></div></header>
  <div class="graph-grid"><?php foreach ($relationships as $item): ?><article class="graph-card <?= !$item['incoming'] && $item['page']->url !== '/' ? 'is-orphan' : '' ?>"><header><div><span><?= $e($item['page']->relativePath) ?></span><strong><?= $e($item['page']->title) ?></strong></div><a href="/admin?file=<?= rawurlencode($item['page']->relativePath) ?>">Edit</a></header><div><section><span>Incoming <?= count($item['incoming']) ?></span><?php foreach ($item['incoming'] as $page): ?><a href="/admin?file=<?= rawurlencode($page->relativePath) ?>"><?= $e($page->title) ?></a><?php endforeach; ?><?php if (!$item['incoming']): ?><small>No incoming links</small><?php endif; ?></section><section><span>Outgoing <?= count($item['outgoing']) ?></span><?php foreach ($item['outgoing'] as $page): ?><a href="/admin?file=<?= rawurlencode($page->relativePath) ?>"><?= $e($page->title) ?></a><?php endforeach; ?><?php if (!$item['outgoing']): ?><small>No outgoing links</small><?php endif; ?></section></div></article><?php endforeach; ?></div>
</main>
</body>
</html>

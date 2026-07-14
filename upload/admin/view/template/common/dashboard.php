<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Studio overview · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root{--brand:<?= $e($config['accent']) ?>}</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
  <header class="page-header">
    <div><p class="panel-eyebrow">Content Studio</p><h1>Documentation overview</h1><p>Write, review, and publish from one focused workspace.</p></div>
    <div class="dashboard-actions"><a class="button secondary-button" href="/admin/health">Review content</a><a class="button" href="/admin/editor">Open editor</a></div>
  </header>

  <nav class="dashboard-tabs" aria-label="Dashboard sections"><a class="is-active" href="/admin">Overview</a><a href="/admin/editor">Content</a><a href="/admin/health">Health</a><a href="/admin/extensions">Extensions</a></nav>

  <section class="dashboard-stats" aria-label="Content summary">
    <a href="/admin/editor"><span>All content</span><strong><?= (int) $stats['pages'] ?></strong><small><?= (int) $stats['published'] ?> published</small></a>
    <a href="/admin/editor"><span>Drafts</span><strong><?= (int) $stats['drafts'] ?></strong><small>Not publicly visible</small></a>
    <a href="/admin/editor"><span>Private</span><strong><?= (int) $stats['private'] ?></strong><small>Authenticated only</small></a>
    <a href="/admin/health"><span>Needs attention</span><strong><?= (int) $stats['issues'] ?></strong><small>Health findings</small></a>
  </section>

  <div class="dashboard-grid">
    <section class="dashboard-panel">
      <header><div><p class="panel-eyebrow">Content</p><h2>Recently updated</h2></div><a href="/admin/editor">Browse all</a></header>
      <div class="recent-content-list">
        <?php foreach ($recent_pages as $page): ?><a href="/admin/editor?file=<?= rawurlencode($page->relative_path) ?>"><span class="page-icon" aria-hidden="true"></span><span><strong><?= $e($page->title) ?></strong><small><?= $e($page->relative_path) ?> · <?= $e(date('M j, Y', $page->modified_at)) ?></small></span><?php if ($page->isPrivate()): ?><i>Private</i><?php elseif ($page->isDraft()): ?><i>Draft</i><?php endif; ?></a><?php endforeach; ?>
      </div>
    </section>

    <aside class="dashboard-column">
      <section class="dashboard-panel index-panel">
        <header><div><p class="panel-eyebrow">SQLite</p><h2>Content index</h2></div><span class="status-dot" aria-label="Synced"></span></header>
        <dl><div><dt>Documents</dt><dd><?= (int) ($index_stats['documents'] ?? 0) ?></dd></div><div><dt>Headings</dt><dd><?= (int) ($index_stats['headings'] ?? 0) ?></dd></div><div><dt>Keywords</dt><dd><?= (int) ($index_stats['keywords'] ?? 0) ?></dd></div><div><dt>Links</dt><dd><?= (int) ($index_stats['links'] ?? 0) ?></dd></div></dl>
        <p>Synced automatically from canonical Markdown.</p>
      </section>
      <section class="dashboard-panel issue-panel">
        <header><div><p class="panel-eyebrow">Review queue</p><h2>Content health</h2></div><a href="/admin/health">View all</a></header>
        <?php if ($issues): ?><div><?php foreach ($issues as $issue): ?><a href="/admin/editor?file=<?= rawurlencode($issue['file']) ?>"><span class="issue-indicator severity-<?= $e($issue['severity']) ?>"></span><span><strong><?= $e($issue['message']) ?></strong><small><?= $e($issue['file']) ?></small></span></a><?php endforeach; ?></div><?php else: ?><div class="dashboard-empty"><span>✓</span><strong>Everything looks healthy</strong><small>No content issues found.</small></div><?php endif; ?>
      </section>
    </aside>
  </div>
</main>
</body>
</html>

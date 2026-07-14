<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Content Health · <?= $e($config['name']) ?></title><link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>"><style>:root{--brand:<?= $e($config['accent']) ?>}</style></head>
<body class="editor-body"><?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard"><header class="page-header"><div><span class="panel-eyebrow">Content Studio</span><h1>Content health</h1><p>Fast checks for publishing quality, missing assets, internal links, and sensitive values.</p></div><a class="button" href="/admin/editor">Open editor</a></header>
<section class="dashboard-stats" aria-label="Content health summary">
<div><span>Total pages</span><strong><?= (int) $health['pages'] ?></strong></div>
<div><span>Private pages</span><strong><?= (int) $health['private'] ?></strong></div>
<div><span>Drafts</span><strong><?= (int) $health['drafts'] ?></strong></div>
<div><span>Items to review</span><strong><?= count($health['issues']) ?></strong></div>
</section>
<?php
$severity_counts = ['error' => 0, 'warning' => 0, 'notice' => 0];
foreach ($health['issues'] as $issue) {
    $severity_counts[$issue['severity']] = ($severity_counts[$issue['severity']] ?? 0) + 1;
}
$severity_summary = implode(' · ', array_filter([
    $severity_counts['error'] ? $severity_counts['error'] . ' error' . ($severity_counts['error'] === 1 ? '' : 's') : '',
    $severity_counts['warning'] ? $severity_counts['warning'] . ' warning' . ($severity_counts['warning'] === 1 ? '' : 's') : '',
    $severity_counts['notice'] ? $severity_counts['notice'] . ' notice' . ($severity_counts['notice'] === 1 ? '' : 's') : '',
]));
?>
<section class="dashboard-panel">
<header><div><p class="panel-eyebrow">Review queue</p><h2>Content health</h2></div><span class="status-pill <?= $severity_counts['error'] ? 'is-disabled' : '' ?>"><?= $health['issues'] ? count($health['issues']) . ' found' : 'All clear' ?></span></header>
<?php if (!$health['issues']): ?><div class="table-empty">No broken internal links, missing uploaded assets, or metadata warnings were found.</div><?php else: ?>
<div class="admin-table-wrap table-borderless"><table class="admin-table"><thead><tr><th>Message</th><th>File</th><th>Line</th><th>Severity</th></tr></thead><tbody>
<?php foreach ($health['issues'] as $issue): $editable = str_ends_with($issue['file'], '.md'); ?>
<tr><td><?php if ($editable): ?><a href="/admin/editor?file=<?= rawurlencode($issue['file']) ?>"><strong><?= $e($issue['message']) ?></strong></a><?php else: ?><strong><?= $e($issue['message']) ?></strong><?php endif; ?></td><td><code><?= $e($issue['file']) ?></code></td><td><?= (int) $issue['line'] ?></td><td><span class="severity-badge severity-<?= $e($issue['severity']) ?>"><?= $e(ucfirst($issue['severity'])) ?></span></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</section></main></body></html>

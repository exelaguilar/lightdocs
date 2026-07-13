<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Content Health · <?= $e($config['name']) ?></title><link rel="stylesheet" href="/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/assets/app.css') ?: 1 ?>"><style>:root{--brand:<?= $e($config['accent']) ?>}</style></head>
<body class="editor-body"><?php require __DIR__ . '/_header.php'; ?>
<main class="health-shell"><header class="health-header"><div><span class="panel-eyebrow">Content Studio</span><h1>Content health</h1><p>Fast checks for publishing quality, missing assets, internal links, and sensitive values.</p></div><a class="button" href="/admin/editor">Open editor</a></header>
<section class="health-stats"><div><strong><?= (int) $health['pages'] ?></strong><span>Total pages</span></div><div><strong><?= (int) $health['private'] ?></strong><span>Private pages</span></div><div><strong><?= (int) $health['drafts'] ?></strong><span>Drafts</span></div><div><strong><?= count($health['issues']) ?></strong><span>Items to review</span></div></section>
<?php
$severityCounts = ['error' => 0, 'warning' => 0, 'notice' => 0];
foreach ($health['issues'] as $issue) {
    $severityCounts[$issue['severity']] = ($severityCounts[$issue['severity']] ?? 0) + 1;
}
$severitySummary = implode(' · ', array_filter([
    $severityCounts['error'] ? $severityCounts['error'] . ' error' . ($severityCounts['error'] === 1 ? '' : 's') : '',
    $severityCounts['warning'] ? $severityCounts['warning'] . ' warning' . ($severityCounts['warning'] === 1 ? '' : 's') : '',
    $severityCounts['notice'] ? $severityCounts['notice'] . ' notice' . ($severityCounts['notice'] === 1 ? '' : 's') : '',
]));
?>
<section class="health-card"><div class="health-card-head"><div><strong>Review queue</strong><span><?= $severitySummary !== '' ? $e($severitySummary) : 'Warnings are advisory unless marked as errors.' ?></span></div><span class="health-score <?= $severityCounts['error'] ? '' : 'healthy' ?>"><?= $health['issues'] ? count($health['issues']) . ' found' : 'All clear' ?></span></div>
<?php if (!$health['issues']): ?><div class="health-empty"><span>✓</span><h2>Your content looks healthy</h2><p>No broken internal links, missing uploaded assets, or metadata warnings were found.</p></div><?php else: ?><div class="health-issues"><?php foreach ($health['issues'] as $issue): $editable = str_ends_with($issue['file'], '.md'); ?><?php if ($editable): ?><a href="/admin?file=<?= rawurlencode($issue['file']) ?>" class="health-issue severity-<?= $e($issue['severity']) ?>"><?php else: ?><div class="health-issue severity-<?= $e($issue['severity']) ?>"><?php endif; ?><span class="issue-indicator"></span><span><strong><?= $e($issue['message']) ?></strong><small><?= $e($issue['file']) ?> · line <?= (int) $issue['line'] ?></small></span><?php if ($editable): ?><i>→</i></a><?php else: ?></div><?php endif; ?><?php endforeach; ?></div><?php endif; ?>
</section></main></body></html>

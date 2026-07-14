<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Audit log · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Observability</span><h1>Audit log</h1><p>Review changes recorded by the optional audit extension. Entries include the event name, source, time, and event payload.</p></div><a class="button secondary-button" href="/admin/extensions/audit/settings">Audit settings</a></header>
	<section class="dashboard-panel management-panel">
		<header><div><p class="panel-eyebrow">Recorded activity</p><h2><?= $total ?> event<?= $total === 1 ? '' : 's' ?></h2></div><span class="status-pill">Retention managed by extension settings</span></header>
		<form class="audit-filters" method="get">
			<label><span>Search</span><input type="search" name="q" value="<?= $e($search) ?>" placeholder="Event or payload"></label>
			<label><span>Event</span><select name="event"><option value="">All events</option><?php foreach ($filters['events'] as $filter_event): ?><option value="<?= $e($filter_event) ?>" <?= $event === $filter_event ? 'selected' : '' ?>><?= $e($filter_event) ?></option><?php endforeach; ?></select></label>
			<label><span>Source</span><select name="source"><option value="">All sources</option><?php foreach ($filters['sources'] as $filter_source): ?><option value="<?= $e($filter_source) ?>" <?= $source === $filter_source ? 'selected' : '' ?>><?= $e($filter_source) ?></option><?php endforeach; ?></select></label>
			<label><span>Sort</span><select name="sort"><option value="desc" <?= $sort === 'desc' ? 'selected' : '' ?>>Newest first</option><option value="asc" <?= $sort === 'asc' ? 'selected' : '' ?>>Oldest first</option></select></label>
			<button class="button" type="submit">Filter</button>
			<?php if ($event || $source || $search): ?><a class="button secondary-button" href="/admin/audit">Clear</a><?php endif; ?>
		</form>
		<?php if (!$entries): ?><div class="table-empty">No audit entries match these filters.</div><?php else: ?>
		<div class="admin-table-wrap table-borderless"><table class="admin-table audit-table"><thead><tr><th>Time</th><th>Event</th><th>Source</th><th>Payload</th></tr></thead><tbody>
		<?php foreach ($entries as $entry): $payload = json_decode((string) $entry['payload_json'], true); if ($payload === null && (string) $entry['payload_json'] === 'null') $payload = ['note' => 'No payload was recorded for this historical entry.']; ?>
			<tr><td><?= $e(date('Y-m-d H:i:s', (int) $entry['created_at'])) ?></td><td><code><?= $e($entry['event']) ?></code></td><td><?= $e($entry['source']) ?></td><td><details class="audit-payload"><summary>View payload</summary><pre><?= $e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}') ?></pre></details></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<nav class="table-pagination audit-pagination" aria-label="Audit log pages"><?php if ($page > 1): ?><a href="?<?= http_build_query(['q' => $search, 'event' => $event, 'source' => $source, 'sort' => $sort, 'page' => $page - 1]) ?>">Previous</a><?php else: ?><span></span><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="?<?= http_build_query(['q' => $search, 'event' => $event, 'source' => $source, 'sort' => $sort, 'page' => $page + 1]) ?>">Next</a><?php else: ?><span></span><?php endif; ?></nav>
		<?php endif; ?>
	</section>
	<aside class="management-intro audit-explainer"><div><p class="panel-eyebrow">What is captured</p><h2>Framework activity, not page visits</h2></div><p>The audit extension currently records <code>content.changed</code>, <code>index.rebuilt</code>, and <code>settings.saved</code>. Content changes include the action, file or asset, and signed-in actor when available. Payloads are stored as JSON and automatically removed after the configured retention period.</p><p>Configure retention and extension state from <a href="/admin/extensions/audit/settings">Audit settings</a>.</p></aside>
</main>
</body>
</html>

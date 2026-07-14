<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Events · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header">
		<div>
			<span class="panel-eyebrow">System</span>
			<h1>Events</h1>
			<p>Synchronous signals that let the core and extensions react to application changes.</p>
		</div>
	</header>
	<div class="management-layout">
		<section class="dashboard-panel management-panel">
			<header>
				<div><p class="panel-eyebrow">Runtime</p><h2>Registered listeners</h2></div>
				<span class="status-pill"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?></span>
			</header>
			<?php if (!$events): ?>
				<div class="table-empty">No event listeners are registered. Listeners are added by the core or an extension during bootstrap.</div>
			<?php else: ?>
				<div class="admin-table-wrap table-borderless"><table class="admin-table"><thead><tr><th>Event code</th><th>Source</th><th>Status</th><th class="table-actions">Actions</th></tr></thead><tbody>
				<?php foreach ($events as $event): ?>
					<tr><td><strong><code><?= $e($event['code']) ?></code></strong><small><?= $e($event['event']) ?><?= !empty($event['description']) ? ' · ' . $e($event['description']) : '' ?></small></td><td><?= $e($event['extension']) ?></td><td><?php if ($event['extension'] === 'core'): ?><span class="status-pill">Core</span><?php else: ?><span class="status-pill <?= $event['enabled'] ? '' : 'is-disabled' ?>"><?= $event['enabled'] ? 'Enabled' : 'Disabled' ?></span><?php endif; ?></td><td class="table-actions"><?php if ($event['extension'] !== 'core'): ?><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="event" value="<?= $e($event['code']) ?>"><input type="hidden" name="enabled" value="<?= $event['enabled'] ? '0' : '1' ?>"><button type="submit" class="text-button"><?= $event['enabled'] ? 'Disable' : 'Enable' ?></button></form><?php endif; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
			<?php endif; ?>
		</section>
		<aside class="management-intro">
			<div><p class="panel-eyebrow">How events work</p><h2>Small hooks between parts</h2></div>
			<p>Events are named signals. Code registers a listener for a name, then the application dispatches that name with a payload when something important happens.</p>
			<ol>
				<li>A controller or service dispatches an event.</li>
				<li>Every registered listener runs in registration order.</li>
				<li>The request continues after listeners finish.</li>
			</ol>
			<p><code>$extensions-&gt;on('content.changed', $listener)</code></p>
			<p class="management-note">Events are synchronous and local. They are useful for cache invalidation, indexing, audit entries, and extension behavior—not for background jobs.</p>
		</aside>
	</div>
	<section class="dashboard-panel event-create-panel">
		<header><div><p class="panel-eyebrow">Custom event</p><h2>Define a signal for your application</h2></div></header>
		<form method="post" class="event-create-form"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="create"><label><span>Event name</span><input name="event_name" pattern="[a-z][a-z0-9_.-]{2,80}" placeholder="content.published" required><small>Use a stable lowercase name. Code must dispatch this name before listeners can run.</small></label><label><span>Description</span><input name="description" maxlength="180" placeholder="Runs after a page becomes publishable."></label><button class="button" type="submit">Add event</button></form>
	</section>
</main>
</body>
</html>

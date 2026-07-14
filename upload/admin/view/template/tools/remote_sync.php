<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Remote sync · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Tools</span><h1>Remote sync</h1><p>Manually synchronize the private documentation repository with a configured Git remote.</p></div><a class="button secondary-button" href="/admin/extensions/remote_sync/settings">Remote sync settings</a></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<section class="dashboard-panel management-panel remote-sync-panel">
		<header><div><p class="panel-eyebrow">Connection status</p><h2>Repository readiness</h2></div><span class="status-pill <?= $status['available'] && $status['repository'] && $status['configured'] ? '' : 'is-disabled' ?>"><?= $status['available'] && $status['repository'] && $status['configured'] ? 'Ready' : 'Needs configuration' ?></span></header>
		<div class="status-grid">
			<div><span>Git executable</span><strong><?= $status['available'] ? 'Available' : 'Unavailable' ?></strong></div>
			<div><span>Local repository</span><strong><?= $status['repository'] ? 'Initialized' : 'Not initialized' ?></strong></div>
			<div><span>Remote URL</span><strong><?= $status['configured'] ? 'Configured' : 'Not configured' ?></strong></div>
			<div><span>Branch</span><strong><?= $e($status['branch']) ?></strong></div>
		</div>
	</section>
	<div class="management-layout">
		<section class="dashboard-panel management-panel">
			<header><div><p class="panel-eyebrow">Manual operations</p><h2>Synchronize repository</h2><p>Pull only uses fast-forward updates. Push is blocked unless explicitly enabled in Remote sync settings.</p></div></header>
			<div class="remote-sync-actions">
				<?php if (!$status['repository']): ?><form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="initialize"><button class="button" type="submit">Import remote repository</button></form><?php endif; ?>
				<form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="pull"><button class="button" type="submit">Pull remote changes</button></form>
				<form method="post"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><input type="hidden" name="action" value="push"><button class="button secondary-button" type="submit">Push local changes</button></form>
			</div>
		</section>
		<aside class="management-intro"><div><p class="panel-eyebrow">Safety notes</p><h2>Keep deployment changes deliberate</h2></div><p>Remote sync never runs on a timer. Configure the remote URL, branch, and credentials from <a href="/admin/extensions/remote_sync/settings">Remote sync settings</a>. Initialize Local Git before attempting a pull or push.</p></aside>
	</div>
</main>
</body>
</html>

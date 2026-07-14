<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Users · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; --brand-strong: color-mix(in srgb,<?= $e($config['accent']) ?> 84%,#220c4d); --brand-soft: color-mix(in srgb,<?= $e($config['accent']) ?> 9%,#fff); }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Access control</span><h1>Users</h1><p>Manage people and permissions for Content Studio.</p></div><a class="button" href="/admin/users/new">Add user</a></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<section class="dashboard-panel table-page-card">
				<header><div><p class="panel-eyebrow">Directory</p><h2>Studio users</h2></div><span class="status-pill"><?= count($users) ?> account<?= count($users) === 1 ? '' : 's' ?></span></header>
				<section class="table-toolbar" aria-label="User table controls"><p><strong>All users</strong><span>Accounts with access to this installation</span></p><input class="table-search" type="search" placeholder="Filter users" data-table-filter="users"></section>
				<?php if (!$users): ?><div class="table-empty">No users yet.</div><?php else: ?>
				<div class="admin-table-wrap table-borderless"><table class="admin-table" data-table="users"><thead><tr><th>User</th><th>Role</th><th>Last sign in</th><th>Status</th><th class="table-actions">Action</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><strong><span class="table-avatar"><?= $e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span><?= $e($user['display_name']) ?></strong><small><?= $e($user['username']) ?></small></td><td><?= $e($user['role_label'] ?? 'Unassigned') ?></td><td><?= $user['last_login'] ? $e(date('M j, Y', (int) $user['last_login'])) : 'Never' ?></td><td><span class="status-pill <?= $user['enabled'] ? '' : 'is-disabled' ?>"><?= $user['enabled'] ? 'Active' : 'Disabled' ?></span></td><td class="table-actions"><a class="table-action-button" href="/admin/users/edit?id=<?= (int) $user['id'] ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16.5-.8 3.8 3.8-.8L18.5 8a2.1 2.1 0 0 0-3-3L4 16.5Z"/><path d="m14 6 3 3"/></svg><span>Edit</span></a></td></tr><?php endforeach; ?></tbody></table></div>
				<footer class="table-pagination"><span>Showing <?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></span><span>1 page</span></footer>
				<?php endif; ?>
			</section>
</main>
</body>
</html>

<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Profile settings · <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard">
	<header class="page-header"><div><span class="panel-eyebrow">Account</span><h1>Profile settings</h1><p>Manage your account details and sign-in preferences.</p></div></header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<section class="dashboard-panel settings-card"><header><div><p class="panel-eyebrow">Personal information</p><h2><?= $e($user['username']) ?></h2><p>Update the name shown in the admin interface.</p></div><span class="status-pill">Active</span></header><form method="post" class="settings-form-compact"><input type="hidden" name="csrf" value="<?= $e($csrf) ?>"><label><span>Display name</span><input name="display_name" value="<?= $e($user['display_name']) ?>" required></label><label><span>New password</span><input type="password" name="password" minlength="12" autocomplete="new-password"><small>Leave blank to keep your current password. New passwords must be at least 12 characters.</small></label><footer><button class="button" type="submit">Save changes</button><a class="button secondary-button" href="/admin">Cancel</a></footer></form></section>
</main>
</body>
</html>

<?php
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
$edit = (bool) ($edit ?? false);
$user = $user ?? null;
$title = $edit ? 'Edit user' : 'Add user';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title><?= $e($title) ?> - <?= $e($config['name']) ?></title>
	<link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
	<style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard user-page">
	<header class="page-header">
		<div><span class="panel-eyebrow">Access control</span><h1><?= $e($title) ?></h1><p><?= $edit ? 'Update account details, role, status, or password.' : 'Create an account and assign the permissions it needs.' ?></p></div>
		<a class="button secondary-button" href="/admin/users">Back to users</a>
	</header>
	<?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
	<?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
	<section class="dashboard-panel add-user-card">
		<header><div><p class="panel-eyebrow"><?= $edit ? 'Account details' : 'New account' ?></p><h2><?= $edit ? $e($user['username']) : 'Create a user' ?></h2><p><?= $edit ? 'Changes apply on the next request.' : 'Users can sign in immediately after creation.' ?></p></div><?php if ($edit): ?><span class="status-pill <?= $user['enabled'] ? '' : 'is-disabled' ?>"><?= $user['enabled'] ? 'Active' : 'Disabled' ?></span><?php endif; ?></header>
		<form method="post" class="user-create-form">
			<input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
			<?php if ($edit): ?><input type="hidden" name="enabled" value="0"><label class="user-toggle"><input type="checkbox" name="enabled" value="1" <?= $user['enabled'] ? 'checked' : '' ?>><span><strong>Account enabled</strong><small>Allow this user to sign in to Content Studio.</small></span></label><?php endif; ?>
			<?php if (!$edit): ?><label><span>Username</span><input name="username" pattern="[A-Za-z0-9._-]{3,80}" required autofocus><small>Letters, numbers, periods, underscores, and dashes.</small></label><?php else: ?><label><span>Username</span><input value="<?= $e($user['username']) ?>" disabled><small>Usernames cannot be changed after creation.</small></label><?php endif; ?>
			<label><span>Display name</span><input name="display_name" value="<?= $edit ? $e($user['display_name']) : '' ?>" required <?= $edit ? '' : 'autofocus' ?>></label>
			<label><span>Role</span><select name="role"><?php foreach ($roles as $role): ?><option value="<?= $e($role['name']) ?>" <?= $edit && ($user['role_name'] ?? '') === $role['name'] ? 'selected' : (!$edit && $role['name'] === 'editor' ? 'selected' : '') ?>><?= $e($role['label']) ?> - <?= $e($role['description']) ?></option><?php endforeach; ?></select></label>
			<label><span><?= $edit ? 'New password' : 'Temporary password' ?></span><input type="password" name="password" minlength="12" <?= $edit ? '' : 'required' ?> autocomplete="new-password"><small><?= $edit ? 'Leave blank to keep the current password.' : 'Use at least 12 characters.' ?></small></label>
			<footer><button class="button" type="submit"><?= $edit ? 'Save user' : 'Create user' ?></button><a class="button secondary-button" href="/admin/users">Cancel</a></footer>
		</form>
	</section>
</main>
</body>
</html>

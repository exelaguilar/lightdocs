<?php

$selected = $role ?? ['name' => '', 'label' => '', 'description' => '', 'permissions' => []];
$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $create ? 'Add role' : 'Edit role' ?> · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard roles-page">
  <header class="page-header">
    <div>
      <span class="panel-eyebrow">Access control</span>
      <h1><?= $create ? 'Add role' : 'Edit role' ?></h1>
      <p><?= $create ? 'Create a focused access policy for Studio users.' : 'Update the permissions assigned to this role.' ?></p>
    </div>
    <a class="button secondary-button" href="/admin/roles">Return to roles</a>
  </header>
  <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <section class="dashboard-panel role-form-card">
    <header>
      <div>
        <p class="panel-eyebrow"><?= $create ? 'New role' : 'Role details' ?></p>
        <h2><?= $create ? 'Create a role' : $e($selected['label']) ?></h2>
        <p>Permissions are additive. Existing accounts receive updates on their next request.</p>
      </div>
    </header>
    <form method="post" class="role-form">
      <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
      <?php require __DIR__ . '/role_form_fields.php'; ?>
      <footer>
        <button class="button" type="submit"><?= $create ? 'Create role' : 'Save role' ?></button>
        <a class="button secondary-button" href="/admin/roles">Cancel</a>
      </footer>
    </form>
  </section>
</main>
</body>
</html>

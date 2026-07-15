<?php

$initial = mb_strtoupper(mb_substr($config['name'], 0, 1));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Roles · <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="editor-body">
<?php require __DIR__ . '/../common/header.php'; ?>
<main class="admin-dashboard roles-page">
  <header class="page-header">
    <div>
      <span class="panel-eyebrow">Access control</span>
      <h1>Roles</h1>
      <p>Define the access policies assigned to Studio users.</p>
    </div>
    <div class="page-header-actions">
      <a class="button secondary-button" href="/admin/users">Manage users</a>
      <a class="button" href="/admin/roles/add">Add role</a>
    </div>
  </header>
  <?php if ($message): ?><p class="form-success" role="status"><?= $e($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
  <section class="dashboard-panel table-page-card">
    <header>
      <div>
        <p class="panel-eyebrow">Role directory</p>
        <h2>Studio roles</h2>
      </div>
      <span class="status-pill"><?= count($roles) ?> role<?= count($roles) === 1 ? '' : 's' ?></span>
    </header>
    <section class="table-toolbar" aria-label="Role table controls">
      <p><strong>All roles</strong><span>Permissions apply to every user assigned to the role.</span></p>
      <input class="table-search" type="search" placeholder="Filter roles" data-table-filter="roles">
    </section>
    <?php if (!$roles): ?>
      <div class="table-empty">No roles have been created yet.</div>
    <?php else: ?>
      <div class="admin-table-wrap table-borderless">
        <table class="admin-table" data-table="roles">
          <thead><tr><th>Role</th><th>Description</th><th>Permissions</th><th class="table-actions">Action</th></tr></thead>
          <tbody>
          <?php foreach ($roles as $role): ?>
            <tr>
              <td><strong><?= $e($role['label']) ?></strong><small><code><?= $e($role['name']) ?></code></small></td>
              <td><?= $e($role['description']) ?></td>
              <td><?= (int) $role['permission_count'] ?> enabled</td>
              <td class="table-actions"><a class="table-action-button" href="/admin/roles/edit?role=<?= rawurlencode($role['name']) ?>">Edit</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <footer class="table-pagination"><span>Showing <?= count($roles) ?> role<?= count($roles) === 1 ? '' : 's' ?></span><span>1 page</span></footer>
    <?php endif; ?>
  </section>
</main>
</body>
</html>

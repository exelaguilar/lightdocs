<?php $initial = mb_strtoupper(mb_substr($config['name'], 0, 1)); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sign in - <?= $e($config['name']) ?></title>
  <link rel="stylesheet" href="/frontend/view/stylesheet/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/frontend/view/stylesheet/app.css') ?: 1 ?>">
  <style>:root { --brand: <?= $e($config['accent']) ?>; }</style>
</head>
<body class="admin-body">
  <main class="login-card">
    <a class="brand" href="/"><span class="brand-mark"><?= $e($initial) ?></span><span><?= $e($config['name']) ?></span></a>
    <div class="login-heading"><span class="panel-eyebrow">Content Studio</span><h1>Welcome back</h1><p>Sign in to manage your Markdown documentation.</p></div>
    <?php if ($error): ?><p class="form-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
    <form method="post">
      <label><span>Username</span><input type="text" name="username" value="admin" required autofocus autocomplete="username"></label>
      <label><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="button" type="submit">Open Content Studio</button>
    </form>
    <?php if ($auth_available): ?><div class="login-divider"><span>or</span></div><a class="button secondary-button login-provider" href="/admin/login/oidc">Continue with SSO</a><?php endif; ?>
    <a class="login-back" href="/">Return to documentation</a>
  </main>
</body>
</html>

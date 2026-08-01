<?php
declare(strict_types=1);
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($title ?? 'Something went wrong') ?> · Lightdocs</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, sans-serif; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8fafc; color: #0f172a; }
    main { width: min(32rem, calc(100% - 2rem)); padding: 2.5rem; border: 1px solid #cbd5e1; border-radius: 1rem; background: white; box-shadow: 0 1rem 3rem #0f172a12; text-align: center; }
    .status { margin: 0; color: #dc2626; font-size: 5rem; font-weight: 800; line-height: 1; }
    h1 { margin: 1rem 0 .5rem; font-size: 1.75rem; }
    p { margin: 0; color: #475569; line-height: 1.6; }
    a { display: inline-block; margin-top: 1.5rem; padding: .65rem 1rem; border-radius: .5rem; background: #0f172a; color: white; text-decoration: none; font-weight: 650; }
    @media (prefers-color-scheme: dark) { body { background: #0f172a; color: #f8fafc; } main { border-color: #334155; background: #1e293b; } p { color: #cbd5e1; } a { background: #f8fafc; color: #0f172a; } }
  </style>
</head>
<body>
  <main>
    <p class="status" aria-label="HTTP status"><?= (int)($status ?? 500) ?></p>
    <h1><?= $e($title ?? 'Something went wrong') ?></h1>
    <p><?= $e($message ?? 'The documentation could not be rendered.') ?></p>
    <a href="/">Return to documentation</a>
  </main>
</body>
</html>

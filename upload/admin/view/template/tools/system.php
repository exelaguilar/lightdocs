<?= $header ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),82rem)] gap-6 py-7 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),82rem)] max-[640px]:gap-5 max-[640px]:py-5">
  <header class="flex items-end justify-between gap-6 max-[640px]:items-stretch max-[640px]:flex-col">
    <div class="grid gap-1">
      <nav class="flex items-center gap-2 text-xs text-muted-foreground" aria-label="Breadcrumb"><a class="transition-colors hover:text-foreground" href="/admin">Workspace</a><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg><span class="text-foreground">System</span></nav>
      <h1 class="m-0 text-2xl font-semibold tracking-[-0.03em] text-foreground">System</h1>
      <p class="m-0 text-sm leading-6 text-muted-foreground">PHP, server, database, and filesystem diagnostics for this installation.</p>
    </div>
    <?php if ($opcache_available): ?>
      <form method="post" action="<?= $e($clear_opcache_url) ?>"><input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>"><button class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md border border-border bg-card px-3.5 py-2 text-sm font-semibold leading-5 text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground max-[640px]:w-full" type="submit">Clear OPcache</button></form>
    <?php endif; ?>
  </header>

  <?php
  $sections = [
    ['title' => 'Framework', 'items' => [
      ['label' => 'Version', 'value' => $framework_version],
      ['label' => 'Server software', 'value' => $server_software],
      ['label' => 'Operating system', 'value' => $server_os],
      ['label' => 'Server timezone', 'value' => $server_timezone],
      ['label' => 'HTTPS', 'value' => $https_enabled ? 'Enabled' : 'Disabled', 'tone' => $https_enabled ? 'good' : 'warn'],
    ]],
    ['title' => 'PHP', 'items' => [
      ['label' => 'Version', 'value' => $php_version],
      ['label' => 'SAPI', 'value' => $php_sapi],
      ['label' => 'Loaded php.ini', 'value' => $php_ini_file],
      ['label' => 'Memory limit', 'value' => $memory_limit],
      ['label' => 'Max execution time', 'value' => $max_execution_time],
      ['label' => 'Upload max filesize', 'value' => $upload_max_filesize],
      ['label' => 'Post max size', 'value' => $post_max_size],
      ['label' => 'Display errors', 'value' => $display_errors ? 'On' : 'Off', 'tone' => $display_errors ? 'warn' : 'good'],
      ['label' => 'OPcache', 'value' => $opcache_enabled ? 'Enabled' : 'Disabled', 'tone' => $opcache_enabled ? 'good' : 'warn'],
    ]],
    ['title' => 'Session', 'items' => [
      ['label' => 'Cookie lifetime', 'value' => $session_cookie_lifetime_label],
      ['label' => 'Save path', 'value' => $session_save_path],
      ['label' => 'SameSite', 'value' => $session_samesite],
    ]],
    ['title' => 'Database', 'items' => [
      ['label' => 'SQLite version', 'value' => $db_version],
      ['label' => 'Database size', 'value' => $db_size_label],
      ['label' => 'Tables', 'value' => (string)$db_table_count],
    ]],
    ['title' => 'Filesystem', 'items' => [
      ['label' => 'Cache directory', 'value' => $cache_writable ? 'Writable' : 'Not writable', 'tone' => $cache_writable ? 'good' : 'bad'],
      ['label' => 'Logs directory', 'value' => $logs_writable ? 'Writable' : 'Not writable', 'tone' => $logs_writable ? 'good' : 'bad'],
      ['label' => 'Content directory', 'value' => $content_writable ? 'Writable' : 'Not writable', 'tone' => $content_writable ? 'good' : 'bad'],
      ['label' => 'Free disk space', 'value' => $free_disk_space_label],
    ]],
  ];
  ?>

  <div class="grid grid-cols-2 gap-5 max-[760px]:grid-cols-1">
    <?php foreach ($sections as $section): ?>
      <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
        <header class="border-b border-border px-5 py-4 max-[640px]:px-4"><h2 class="m-0 text-base font-semibold tracking-[-0.01em]"><?= $e($section['title']) ?></h2></header>
        <dl class="grid gap-0 divide-y divide-border">
          <?php foreach ($section['items'] as $item): ?>
            <div class="flex items-center justify-between gap-4 px-5 py-2.5 text-xs max-[640px]:px-4"><dt class="text-muted-foreground"><?= $e($item['label']) ?></dt><dd class="m-0 max-w-[16rem] truncate text-end font-medium <?= ($item['tone'] ?? '') === 'good' ? 'text-emerald-600 dark:text-emerald-400' : (($item['tone'] ?? '') === 'bad' ? 'text-destructive' : (($item['tone'] ?? '') === 'warn' ? 'text-amber-700 dark:text-amber-300' : 'text-foreground')) ?>" title="<?= $e($item['value']) ?>"><?= $e($item['value']) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>
    <?php endforeach; ?>
  </div>

  <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
    <header class="border-b border-border px-5 py-4 max-[640px]:px-4"><h2 class="m-0 text-base font-semibold tracking-[-0.01em]">Loaded PHP extensions</h2></header>
    <p class="m-0 px-5 py-4 font-mono text-xs leading-6 text-muted-foreground max-[640px]:px-4"><?= $e($loaded_extensions) ?></p>
  </section>
</main>
<?= $footer ?>

<?= $header ?>
<main class="grid w-[min(26rem,100%)] overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm">
  <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
    <div class="grid gap-1.5">
      <span class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground"><?= $e($text['text_forgot_panel']) ?></span>
      <h1 class="m-0 text-[1.375rem] font-[650]"><?= $e($text['heading_common_forgot']) ?></h1>
      <p><?= $e($text['text_forgot_intro']) ?></p>
    </div>
    <?php if ($sent): ?>
      <div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground shadow-sm" role="status"><?= $e($text['text_forgot_sent']) ?></div>
    <?php else: ?>
      <form method="post" class="grid gap-3">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <div role="group" class="grid min-w-0 gap-1.5"><label for="forgot-identifier"><?= $e($text['text_forgot_identifier']) ?></label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground" id="forgot-identifier" type="text" name="identifier" required autofocus autocomplete="username"></div>
        <button class="inline-flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-1.5 text-sm font-semibold leading-5 text-primary-foreground transition-colors hover:bg-primary/90" type="submit"><?= $e($text['text_forgot_submit']) ?></button>
      </form>
    <?php endif; ?>
    <a class="justify-self-start text-xs text-muted-foreground hover:text-foreground" href="/admin/login"><?= $e($text['text_forgot_back']) ?></a>
  </section>
</main>
<?= $footer ?>

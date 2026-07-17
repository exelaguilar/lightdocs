<?= $header ?>
<main class="grid w-[min(64rem,100%)] grid-cols-[minmax(0,1.05fr)_minmax(20rem,0.95fr)] overflow-hidden rounded-lg border border-border bg-card text-sm shadow-sm max-[760px]:grid-cols-1">
  <section class="grid content-between gap-8 bg-[color-mix(in_srgb,var(--primary)_6%,var(--card))] p-10 max-[760px]:hidden">
    <a class="inline-flex items-center gap-2 text-sm font-semibold text-foreground" href="/"><span class="relative inline-flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary text-primary-foreground text-xs"><span><?= $e($initial) ?></span></span><span><?= $e($config['name']) ?></span></a>
    <div>
      <span class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground"><?= $e($text['text_login_eyebrow']) ?></span>
      <h1 class="mt-2 max-w-md text-[clamp(1.5rem,2.6vw,2rem)] leading-[1.15] tracking-[-0.02em]"><?= $e($text['text_login_headline']) ?></h1>
      <p class="mt-3 max-w-[26rem] text-sm leading-[1.6] text-muted-foreground"><?= $e($text['text_login_copy']) ?></p>
    </div>
    <p class="text-xs text-muted-foreground"><?= $e($text['text_login_footnote']) ?></p>
  </section>
  <section class="grid gap-4 rounded-lg border border-border bg-card p-5 text-card-foreground shadow-sm">
    <div class="grid gap-1.5">
      <span class="m-0 text-xs font-bold uppercase tracking-[0.08em] text-muted-foreground"><?= $e($text['text_login_panel']) ?></span>
      <h1 class="m-0 text-[1.375rem] font-[650]"><?= $e($text['text_login_welcome']) ?></h1>
      <p><?= $e($text['text_login_intro']) ?></p>
    </div>
    <?php if ($error): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" class="grid gap-3">
      <div role="group" class="grid min-w-0 gap-1.5"><label for="login-username"><?= $e($text['text_login_username']) ?></label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground" id="login-username" type="text" name="username" value="admin" required autofocus autocomplete="username"></div>
      <div role="group" class="grid min-w-0 gap-1.5"><label for="login-password"><?= $e($text['text_login_password']) ?></label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground" id="login-password" type="password" name="password" required autocomplete="current-password"></div>
      <button class="inline-flex items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3 py-1.5 text-sm font-semibold leading-5 text-primary-foreground transition-colors hover:bg-primary/90" type="submit"><?= $e($text['text_login_submit']) ?></button>
    </form>
    <a class="justify-self-start text-xs text-muted-foreground hover:text-foreground" href="/"><?= $e($text['text_login_back']) ?></a>
  </section>
</main>
<?= $footer ?>

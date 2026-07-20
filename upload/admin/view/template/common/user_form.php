<?= $header ?>
<?php $is_current_user = $edit && !empty($user['is_current_user']); ?>
<main class="mx-auto grid w-[min(calc(100%-3rem),82rem)] gap-6 py-7 pb-10 text-sm max-[900px]:w-[min(calc(100%-2rem),82rem)] max-[640px]:gap-5 max-[640px]:py-5">
  <header class="flex items-end justify-between gap-6 max-[640px]:items-stretch max-[640px]:flex-col">
    <div class="grid gap-1">
      <nav class="flex items-center gap-2 text-xs text-muted-foreground" aria-label="Breadcrumb">
        <a class="transition-colors hover:text-foreground" href="/admin">Workspace</a>
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <a class="transition-colors hover:text-foreground" href="/admin/users">Users</a>
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        <span class="max-w-40 truncate text-foreground"><?= $edit ? $e($user['display_name']) : 'New user' ?></span>
      </nav>
      <h1 class="m-0 text-2xl font-semibold tracking-[-0.03em] text-foreground"><?= $edit ? $e($user['display_name']) : 'Add user' ?></h1>
      <p class="m-0 text-sm leading-6 text-muted-foreground"><?= $edit ? 'Manage this account’s access, identity, and security settings.' : 'Create an account and assign the access it needs.' ?></p>
    </div>
    <a class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md border border-border bg-card px-3.5 py-2 text-sm font-semibold leading-5 text-foreground shadow-sm transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 max-[640px]:w-full" href="/admin/users">
      <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
      <span>Back to users</span>
    </a>
  </header>

  <?php if (!empty($message)): ?><div class="rounded-lg border border-border bg-card px-4 py-3 text-sm text-foreground shadow-sm" role="status"><?= $e($message) ?></div><?php endif; ?>
  <?php if (!empty($error)): ?><div class="rounded-lg border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive" role="alert"><?= $e($error) ?></div><?php endif; ?>

  <div class="grid grid-cols-[15rem_minmax(0,1fr)] items-start gap-5 max-[760px]:grid-cols-1">
    <?php if ($edit): ?>
      <aside class="grid gap-4 rounded-xl border border-border bg-card p-5 text-card-foreground shadow-sm max-[760px]:grid-cols-2 max-[560px]:grid-cols-1 max-[640px]:rounded-lg">
        <div class="grid justify-items-center gap-3 border-b border-border pb-5 text-center max-[760px]:justify-items-start max-[760px]:border-b-0 max-[760px]:border-r max-[760px]:pr-5 max-[560px]:justify-items-center max-[560px]:border-r-0 max-[560px]:border-b max-[560px]:pb-5 max-[560px]:pr-0">
          <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-xl font-semibold text-primary"><?= $e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span>
          <div class="grid gap-0.5"><strong class="text-base font-semibold text-foreground"><?= $e($user['display_name']) ?></strong><span class="text-xs text-muted-foreground">@<?= $e($user['username']) ?></span></div>
          <span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $user['enabled'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground' ?>"><span class="h-1.5 w-1.5 rounded-full <?= $user['enabled'] ? 'bg-emerald-500' : 'bg-muted-foreground/50' ?>"></span><?= $user['enabled'] ? 'Active account' : 'Disabled account' ?></span>
        </div>
        <dl class="grid gap-3 text-xs max-[760px]:content-start max-[560px]:grid-cols-2">
          <div class="grid gap-0.5"><dt class="text-muted-foreground">Role</dt><dd class="m-0 font-medium text-foreground"><?= $e($user['role_label']) ?></dd></div>
          <div class="grid gap-0.5"><dt class="text-muted-foreground">Added</dt><dd class="m-0 font-medium text-foreground"><?= $e($user['date_added_label']) ?></dd></div>
          <div class="grid gap-0.5"><dt class="text-muted-foreground">Last sign in</dt><dd class="m-0 font-medium text-foreground"><?= $e($user['last_login_label']) ?></dd></div>
          <div class="grid gap-0.5"><dt class="text-muted-foreground">Last IP</dt><dd class="m-0 font-medium text-foreground"><?= $e((string)($user['ip'] ?? 'Unknown')) ?></dd></div>
        </dl>
      </aside>
    <?php endif; ?>

    <section class="overflow-hidden rounded-xl border border-border bg-card text-card-foreground shadow-sm max-[640px]:rounded-lg">
      <header class="border-b border-border px-5 py-4 max-[640px]:px-4">
        <p class="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground"><?= $edit ? 'Account settings' : 'New account' ?></p>
        <h2 class="mt-1 text-base font-semibold tracking-[-0.01em] text-foreground"><?= $edit ? 'Account details' : 'Create a user' ?></h2>
        <p class="mt-1 text-xs leading-5 text-muted-foreground"><?= $edit ? 'Changes apply after you save this account.' : 'Users can sign in immediately after creation.' ?></p>
      </header>

      <?php if ($edit && !empty($user['is_protected'])): ?>
        <div class="mx-5 mt-4 flex gap-3 rounded-lg border border-amber-300 bg-amber-100 px-3.5 py-3 text-xs font-medium text-amber-950 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-100 max-[640px]:mx-4" role="note">
          <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 7v5c0 4.7 3.4 8.7 8 10 4.6-1.3 8-5.3 8-10V7l-8-4Z"/><path d="M12 8v4M12 16h.01"/></svg>
          <p class="m-0 leading-5">This is a protected administrator account. Only a super administrator can change its role or status.</p>
        </div>
      <?php endif; ?>

      <form method="post" class="grid gap-0">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">

        <section class="grid gap-4 border-b border-border px-5 py-5 max-[640px]:px-4" aria-labelledby="identity-heading">
          <div><h3 id="identity-heading" class="m-0 text-sm font-semibold text-foreground">Identity</h3><p class="mt-1 text-xs text-muted-foreground">The name, username, and recovery email shown throughout the Studio.</p></div>
          <div class="grid grid-cols-2 gap-4 max-[560px]:grid-cols-1">
            <div class="grid gap-1.5"><label class="text-xs font-medium text-foreground" for="user-display-name">Display name</label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" id="user-display-name" name="display_name" value="<?= $edit ? $e($user['display_name']) : '' ?>" required <?= $edit ? '' : 'autofocus' ?>></div>
            <div class="grid gap-1.5"><label class="text-xs font-medium text-foreground" for="user-username">Username</label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" id="user-username" name="username" value="<?= $edit ? $e($user['username']) : '' ?>" pattern="[A-Za-z0-9._-]{3,80}" required aria-describedby="user-username-help"><p id="user-username-help" class="m-0 text-xs text-muted-foreground">3–80 letters, numbers, periods, underscores, or dashes.</p></div>
            <div class="grid gap-1.5"><label class="text-xs font-medium text-foreground" for="user-email">Email</label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" id="user-email" type="email" name="email" value="<?= $edit ? $e((string)($user['email'] ?? '')) : '' ?>" aria-describedby="user-email-help"><p id="user-email-help" class="m-0 text-xs text-muted-foreground">Required for this account to use "Forgot password".</p></div>
          </div>
        </section>

        <section class="grid gap-4 border-b border-border px-5 py-5 max-[640px]:px-4" aria-labelledby="access-heading">
          <div><h3 id="access-heading" class="m-0 text-sm font-semibold text-foreground">Access</h3><p class="mt-1 text-xs text-muted-foreground">Choose the role and whether this account can sign in.</p></div>
          <div class="grid grid-cols-2 gap-4 max-[560px]:grid-cols-1">
            <div class="grid gap-1.5"><label class="text-xs font-medium text-foreground" for="user-role">Role</label><?php if ($edit && !empty($user['is_protected']) && empty($user['can_edit_protected'])): ?><input type="hidden" name="user_group_id" value="<?= (int)$user['user_group_id'] ?>"><?php endif; ?><select class="min-h-9 w-full cursor-pointer rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none focus:border-ring focus:ring-2 focus:ring-ring/20 disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground" id="user-role" name="user_group_id" <?= $edit && !empty($user['is_protected']) && empty($user['can_edit_protected']) ? 'disabled' : '' ?>><?php foreach ($roles as $role): ?><option value="<?= (int)$role['user_group_id'] ?>" <?= $edit && (int)($user['user_group_id'] ?? 0) === (int)$role['user_group_id'] ? 'selected' : (!$edit && $role['name'] === 'Editor' ? 'selected' : '') ?>><?= $e($role['name']) ?><?= $role['description'] !== '' ? ' — ' . $e($role['description']) : '' ?></option><?php endforeach; ?></select><p class="m-0 text-xs text-muted-foreground"><a class="text-primary underline-offset-4 hover:underline" href="/admin/roles">Manage roles</a> and their permissions.</p></div>
            <?php if ($edit): ?><div class="flex items-start justify-between gap-4 rounded-lg border border-border bg-muted/30 p-3"><div class="grid gap-0.5"><label class="text-xs font-medium text-foreground" for="user-enabled">Account enabled</label><p class="m-0 text-xs leading-5 text-muted-foreground"><?= $is_current_user ? 'Your own account cannot be disabled here.' : 'Allow this user to sign in.' ?></p></div><?php $enabled_locked = $is_current_user || (!empty($user['is_protected']) && empty($user['can_edit_protected'])); ?><input type="hidden" name="enabled" value="<?= $enabled_locked && $user['enabled'] ? '1' : '0' ?>"><input type="checkbox" role="switch" class="relative mt-0.5 h-5 w-9 shrink-0 cursor-pointer appearance-none rounded-full border border-input bg-muted transition-colors checked:border-primary checked:bg-primary after:absolute after:left-0.5 after:top-0.5 after:h-3.5 after:w-3.5 after:rounded-full after:bg-white after:transition-transform after:content-[''] checked:after:translate-x-4 disabled:cursor-not-allowed disabled:opacity-50" id="user-enabled" name="enabled" value="1" <?= $user['enabled'] ? 'checked' : '' ?> <?= $enabled_locked ? 'disabled' : '' ?>></div><?php else: ?><div class="grid gap-1.5"><span class="text-xs font-medium text-foreground">Account status</span><span class="text-xs text-muted-foreground">New accounts are enabled automatically.</span></div><?php endif; ?>
          </div>
        </section>

        <section class="grid gap-4 px-5 py-5 max-[640px]:px-4" aria-labelledby="security-heading">
          <div><h3 id="security-heading" class="m-0 text-sm font-semibold text-foreground">Security</h3><p class="mt-1 text-xs text-muted-foreground"><?= $edit ? 'Set a new password only when you need to rotate it.' : 'Use a strong password with at least 12 characters.' ?></p></div>
          <div class="grid max-w-xl gap-1.5"><label class="text-xs font-medium text-foreground" for="user-password"><?= $edit ? 'New password' : 'Temporary password' ?></label><input class="min-h-9 w-full rounded-md border border-input bg-card px-2.5 py-2 text-sm text-foreground shadow-sm outline-none transition-colors placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/20" id="user-password" type="password" name="password" minlength="12" <?= $edit ? '' : 'required' ?> autocomplete="new-password"><p class="m-0 text-xs text-muted-foreground"><?= $edit ? 'Leave blank to keep the current password.' : 'At least 12 characters is required.' ?></p></div>
        </section>

        <footer class="flex items-center justify-between gap-3 border-t border-border bg-muted/20 px-5 py-4 max-[560px]:items-stretch max-[560px]:flex-col max-[640px]:px-4">
          <span class="text-xs text-muted-foreground"><?= $edit ? 'Review the changes before saving.' : 'You can edit this account later.' ?></span>
          <div class="flex items-center gap-2 max-[560px]:flex-col-reverse"><a class="inline-flex min-h-9 items-center justify-center rounded-md border border-border bg-card px-3.5 py-2 text-sm font-semibold leading-5 text-foreground transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring max-[560px]:w-full" href="/admin/users">Cancel</a><button class="inline-flex min-h-9 items-center justify-center gap-2 rounded-md border border-primary bg-primary px-3.5 py-2 text-sm font-semibold leading-5 text-primary-foreground shadow-sm transition-colors hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 max-[560px]:w-full" type="submit"><svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2Z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg><span><?= $edit ? 'Save changes' : 'Create user' ?></span></button></div>
        </footer>
      </form>
    </section>
  </div>
</main>
<?= $footer ?>

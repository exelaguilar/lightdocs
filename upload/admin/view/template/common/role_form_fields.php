<label><span>Role name</span><input name="name" value="<?= $e($selected['name']) ?>" pattern="[a-z][a-z0-9_-]{2,40}" <?= $create ? 'required' : 'readonly' ?>><small>Lowercase identifier used internally.</small></label>
<label><span>Label</span><input name="label" value="<?= $e($selected['label']) ?>" required></label>
<label><span>Description</span><input name="description" value="<?= $e($selected['description']) ?>"></label>
<fieldset>
  <legend>Permissions</legend>
  <div class="permission-actions"><span>Choose the Studio areas this role can use.</span><button class="text-button" type="button" data-permissions-select-all>Select all</button></div>
  <div class="permission-grid">
    <?php foreach ($available_permissions as $permission => $definition): ?>
      <label>
        <input type="checkbox" name="permissions[]" value="<?= $e($permission) ?>" <?= in_array($permission, $selected['permissions'], true) ? 'checked' : '' ?> data-role-permission>
        <span><strong><?= $e($definition['label']) ?></strong><small><?= $e($definition['description']) ?></small><code><?= $e($permission) ?></code></span>
      </label>
    <?php endforeach; ?>
  </div>
</fieldset>

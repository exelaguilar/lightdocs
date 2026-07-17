<?php if ($shell): ?>
</div>
<?php endif; ?>
<div id="toaster" class="fixed bottom-4 right-4 z-[200] grid w-[min(24rem,calc(100vw-2rem))] gap-2" aria-live="polite" aria-atomic="true"></div>
<?php foreach ($scripts as $script): ?>
<script src="<?= $e($script['href']) ?>"<?php foreach ($script['attributes'] as $attribute => $value): ?><?= $value === true ? ' ' . $e($attribute) : ' ' . $e($attribute) . '="' . $e($value) . '"' ?><?php endforeach; ?>></script>
<?php endforeach; ?>
</body>
</html>

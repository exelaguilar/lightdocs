<?php

// system/vendor.php

require DIR_ROOT . 'vendor/autoload.php';

// Composer's optimized classmap is regenerated during release builds. This
// small fallback keeps convention-based MVC classes available during local
// development when a new class has not been added to the generated map yet.
spl_autoload_register(static function (string $class_name): void {
	$roots = [
		'Admin\\Controller\\' => DIR_ROOT . 'admin/controller',
		'Frontend\\Controller\\' => DIR_ROOT . 'frontend/controller',
		'System\\Engine\\' => DIR_SYSTEM . 'engine',
		'System\\Library\\' => DIR_SYSTEM . 'library',
		'System\\Model\\' => DIR_SYSTEM . 'model',
		'System\\Console\\' => DIR_SYSTEM . 'console',
		'Extension\\' => DIR_ROOT . 'extension',
	];
	foreach ($roots as $namespace => $root) {
		if (!str_starts_with($class_name, $namespace)) continue;
		$short_name = strtolower(substr($class_name, strlen($namespace)));
		$direct_path = $root . '/' . str_replace('\\', '/', $short_name) . '.php';
		if (is_file($direct_path)) {
			require $direct_path;
			return;
		}
		if (!is_dir($root)) return;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if (strtolower($file->getFilename()) !== basename($short_name) . '.php') continue;
			require $file->getPathname();
			return;
		}
	}
});

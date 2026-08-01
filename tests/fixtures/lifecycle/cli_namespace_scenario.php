<?php

declare(strict_types=1);

namespace {
    require dirname(__DIR__, 3) . '/upload/vendor/autoload.php';
}

namespace System\Console {
    final class Console
    {
        public function __construct()
        {
        }

        public function run(array $arguments): int
        {
            $paths = [];
            foreach (spl_autoload_functions() as $callback) {
                if (!$callback instanceof \Closure) {
                    continue;
                }
                $owner = (new \ReflectionFunction($callback))->getClosureThis();
                if (!$owner instanceof \System\Engine\Autoloader) {
                    continue;
                }
                $property = new \ReflectionProperty($owner, 'path');
                $paths = array_keys((array) $property->getValue($owner));
            }
            echo json_encode([
                'context' => APP_CONTEXT,
                'namespaces' => $paths,
                'dir_system' => DIR_SYSTEM,
                'dir_root' => DIR_ROOT,
            ], JSON_THROW_ON_ERROR) . PHP_EOL;
            return 0;
        }
    }
}

namespace {
    $cli_state = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lightdocs-cli-fixture-' . getmypid();
    @mkdir($cli_state . '/logs', 0775, true);
    @mkdir($cli_state . '/cache', 0775, true);
    putenv('LIGHTDOCS_STATE_DIR=' . $cli_state);
    require dirname(__DIR__, 3) . '/bin/docs';
}

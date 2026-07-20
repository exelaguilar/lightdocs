<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$projectRoot = dirname(__DIR__, 3);
$scenario = $argv[1] ?? 'standard';
require $projectRoot . '/upload/system/startup.php';
$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);

$request = new \System\Library\Request();
$response = new \System\Library\Response();
$response->setRequest($request);

register_shutdown_function(static function () use ($scenario): void {
    if (in_array($scenario, ['redirect', 'file', 'missing-file'], true)) {
        echo "\n[shutdown status=" . var_export(http_response_code(), true) . ' headers=' . json_encode(headers_list()) . ']';
    }
});

switch ($scenario) {
    case 'standard':
        $response->setStatusCode(201);
        $response->addHeader('X-Lifecycle: standard');
        $response->setOutput('body');
        $response->output();
        echo '|status=' . http_response_code() . '|headers=' . json_encode(headers_list());
        break;

    case 'filter':
        $response->addFilter(static fn (string $body): string => strtoupper($body));
        $response->setOutput('filtered');
        $response->output();
        break;

    case 'compression':
        $request->server['HTTP_ACCEPT_ENCODING'] = 'gzip';
        $response->setCompression(6);
        $response->setOutput('compressed-body');
        $response->output();
        break;

    case 'empty':
        $response->setStatusCode(204);
        $response->output();
        echo 'after-empty:status=' . var_export(http_response_code(), true);
        break;

    case 'repeated':
        $response->setOutput('repeat');
        $response->output();
        $response->output();
        break;

    case 'headers-sent':
        echo 'prefix|';
        $response->addHeader('X-Lifecycle: late');
        $response->setOutput('late-body');
        $response->output();
        echo '|after';
        break;

    case 'redirect':
        $response->redirect('/target', 307);
        echo 'after-redirect';
        break;

    case 'file':
        $response->file((string) getenv('LIGHTDOCS_TEST_FILE'), 'text/plain', 'inline');
        echo 'after-file';
        break;

    case 'missing-file':
        $response->file((string) getenv('LIGHTDOCS_TEST_FILE'), 'text/plain', 'inline');
        echo 'after-missing-file';
        break;

    default:
        fwrite(STDERR, 'Unknown response scenario.');
        exit(64);
}

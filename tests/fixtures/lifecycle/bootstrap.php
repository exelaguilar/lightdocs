<?php

declare(strict_types=1);

$encodedServer = getenv('LIGHTDOCS_TEST_SERVER_JSON');
if ($encodedServer !== false && $encodedServer !== '') {
    $decoded = json_decode((string) base64_decode($encodedServer, true), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Lifecycle server fixture must decode to an array.');
    }
    foreach ($decoded as $key => $value) {
        $_SERVER[(string) $key] = $value;
    }
}

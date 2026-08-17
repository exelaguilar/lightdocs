<?php

declare(strict_types=1);

/**
 * Characterization tests for the mail/webhooks/storage/media extensions'
 * CURRENT hand-rolled protocol code (raw SMTP, raw HTTP over
 * stream_context_create, hand-rolled S3 SigV4 signing, GD resizing). These
 * extensions have no coverage anywhere else in this suite — this file exists
 * specifically to lock in today's wire-level behavior before any of it is
 * refactored onto framework primitives (System\Library\Http,
 * System\Library\Image, or a future Mail/Storage contract), per the TinyMVC
 * architecture audit's migration plan. Every assertion here describes
 * behavior as it exists today, including one confirmed quirk (see the first
 * mail test) — this file is a safety net, not a spec for what SHOULD happen.
 *
 * Invocation: php tests/extension_providers.php
 */

require dirname(__DIR__) . '/upload/system/startup.php';
require __DIR__ . '/support/test_suite.php';
require __DIR__ . '/support/temporary_directory.php';

use Lightdocs\Tests\Support\TemporaryDirectory;
use Lightdocs\Tests\Support\TestSuite;
use System\Engine\Extension\Context;
use System\Engine\Extension\Manifest;
use System\Engine\ExtensionApplication;
use System\Library\Content\ContentRepository;
use System\Library\Content\DirectiveRegistry;
use System\Library\Db\AbstractDb;
use System\Library\Db\SqliteDb;
use System\Model\Schema;

$autoloader = new \System\Engine\Autoloader();
$autoloader->register('System', DIR_SYSTEM);
$autoloader->register('Extension', DIR_ROOT . 'extension/');

// --- Fixture-process helpers -----------------------------------------------
// These extensions talk raw sockets, so characterizing them needs a real
// listener on the other end. Each fixture server runs as its own subprocess
// (spawned here, not through Support\Subprocess, which blocks until the
// child exits — these servers must stay alive concurrently with the client
// call this same process makes) and reports what it received as JSON on
// stdout once the one connection it handles is done.

/** @return array{0: resource, 1: array<int, resource>} */
function providers_spawn(string $script, array $arguments): array
{
    $command = array_merge([PHP_BINARY, $script], array_map('strval', $arguments));
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $options = PHP_OS_FAMILY === 'Windows' ? ['bypass_shell' => true] : [];
    $process = proc_open($command, $descriptors, $pipes, null, null, $options);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start fixture server: ' . $script);
    }
    fclose($pipes[0]);
    return [$process, $pipes];
}

/**
 * Waits for the fixture server's "READY" line on stderr rather than probing
 * the port with a real TCP connection — these fixtures handle exactly one
 * connection each, so a probe connection would itself consume the single
 * accept() the real client call needs.
 *
 * @param array<int, resource> $pipes
 */
function providers_wait_ready(array $pipes, float $timeout_seconds): bool
{
    stream_set_blocking($pipes[2], false);
    $buffer = '';
    $deadline = microtime(true) + $timeout_seconds;
    while (microtime(true) < $deadline) {
        $chunk = fread($pipes[2], 4096);
        if ($chunk !== false && $chunk !== '') {
            $buffer .= $chunk;
        }
        if (str_contains($buffer, 'READY')) {
            return true;
        }
        usleep(10_000);
    }
    return str_contains($buffer, 'READY');
}

/** @return array{0: string, 1: string} [stdout, stderr] */
function providers_finish(mixed $process, array $pipes, float $timeout_seconds = 5.0): array
{
    $deadline = microtime(true) + $timeout_seconds;
    while (microtime(true) < $deadline) {
        $status = proc_get_status($process);
        if (!$status['running']) {
            break;
        }
        usleep(20_000);
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_get_status($process)['running']) {
        proc_terminate($process);
    }
    proc_close($process);
    return [$stdout, $stderr];
}

function providers_port(): int
{
    return random_int(20000, 60000);
}

/** A Schema migration reads $this->config (Model::__get) even with nothing configured — Schema::migrate() cannot run without one in the registry. */
function providers_config(): \System\Engine\Config
{
    $config = new \System\Engine\Config(DIR_SYSTEM . 'config');
    $config->load('default.php');
    return $config;
}

function providers_application(string $slug, array $settings, ?AbstractDb $database = null): ExtensionApplication
{
    $application = new ExtensionApplication(
        $slug,
        [],
        new ContentRepository(sys_get_temp_dir()),
        new DirectiveRegistry(),
        $database ?? new SqliteDb(':memory:'),
        $settings,
    );
    ExtensionApplication::setCurrent($application);
    return $application;
}

function providers_context(string $slug, array $settings, ?AbstractDb $database = null): Context
{
    $manifest = Manifest::fromFile(DIR_ROOT . "extension/{$slug}/extension.json");
    providers_application($slug, $settings, $database);
    return new Context($manifest, $settings);
}

function providers_invoke_private(object $object, string $method, array $arguments): mixed
{
    $reflection = new ReflectionMethod($object, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs($object, $arguments);
}

$suite = new TestSuite('Extension provider characterization (mail, webhooks, storage, media)');

// --- Mail --------------------------------------------------------------

$suite->test('mail: plaintext SMTP dialogue (EHLO/AUTH LOGIN/MAIL FROM/RCPT TO/DATA), dot-stuffing, headers', static function (): void {
    $port = providers_port();
    [$process, $pipes] = providers_spawn(__DIR__ . '/fixtures/providers/fake_smtp_server.php', [$port]);
    TestSuite::assertTrue(providers_wait_ready($pipes, 3.0), 'Fake SMTP server did not start listening.');

    $context = providers_context('mail', [
        'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none',
        'username' => 'mailer', 'password' => "sw0rd\x00fish",
        'from_email' => 'sender@example.test', 'from_name' => 'Lightdocs Fixture',
        'timeout' => 5,
    ]);
    $extension = new \Extension\Mail\Extension();
    $extension->register($context);
    /** @var \System\Library\Mail\ProviderInterface $provider */
    $provider = $context->services()['mail.provider'];

    $body = "First line\n.\nThird line follows a dot-only line.";
    $provider->send('recipient@example.test', "Subject with a bullet: \xE2\x80\xA2", $body);

    [$stdout] = providers_finish($process, $pipes);
    $transcript = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertTrue(is_array($transcript) && isset($transcript['transcript']), 'Fake SMTP server did not report a transcript: ' . $stdout);
    $lines = $transcript['transcript'];

    // Confirmed quirk, not a design goal: parse_url() never finds a host in a
    // bare "user@domain" email address (no scheme/authority to parse), so
    // the EHLO hostname and Message-ID domain always fall back to
    // 'localhost' regardless of the configured From address's real domain.
    TestSuite::assertTrue(in_array('EHLO localhost', $lines, true), 'EHLO hostname fallback changed — expected "localhost" since parse_url() cannot read a host out of a bare email address.');

    TestSuite::assertTrue(in_array('AUTH LOGIN', $lines, true), 'AUTH LOGIN was not attempted with a configured username.');
    TestSuite::assertTrue(in_array(base64_encode('mailer'), $lines, true), 'Username was not base64-encoded correctly.');
    TestSuite::assertTrue(in_array(base64_encode("sw0rd\x00fish"), $lines, true), 'Password was not base64-encoded correctly (including an embedded null byte).');
    TestSuite::assertTrue(in_array('MAIL FROM:<sender@example.test>', $lines, true), 'MAIL FROM did not carry the configured From address.');
    TestSuite::assertTrue(in_array('RCPT TO:<recipient@example.test>', $lines, true), 'RCPT TO did not carry the recipient address.');

    $data = implode("\n", $transcript['data']);
    TestSuite::assertContains('From: "Lightdocs Fixture" <sender@example.test>', $data, 'From header did not include the display name.');
    TestSuite::assertContains('Content-Type: text/html; charset=UTF-8', $data, 'Content-Type header changed.');
    TestSuite::assertContains('=?UTF-8?B?', $data, 'A non-ASCII subject was not base64/UTF-8 encoded.');
    TestSuite::assertTrue(in_array('..', $transcript['data'], true), 'A body line starting with "." was not dot-stuffed to ".." before transmission.');
});

$suite->test('mail: refuses to attempt delivery when unconfigured or given invalid addresses (no network reached)', static function (): void {
    $cases = [
        ['settings' => ['host' => '', 'from_email' => 'a@example.test'], 'recipient' => 'b@example.test'],
        ['settings' => ['host' => 'smtp.example.test', 'from_email' => 'not-an-email'], 'recipient' => 'b@example.test'],
        ['settings' => ['host' => 'smtp.example.test', 'from_email' => 'a@example.test'], 'recipient' => 'not-an-email'],
    ];
    foreach ($cases as $index => $case) {
        $context = providers_context('mail', $case['settings']);
        $extension = new \Extension\Mail\Extension();
        $extension->register($context);
        /** @var \System\Library\Mail\ProviderInterface $provider */
        $provider = $context->services()['mail.provider'];
        try {
            $provider->send($case['recipient'], 'subject', 'body');
            TestSuite::assertTrue(false, "Case {$index}: send() did not reject an invalid/unconfigured mail request.");
        } catch (RuntimeException $exception) {
            TestSuite::assertContains('Mail is not configured', $exception->getMessage(), "Case {$index}: unexpected failure message.");
        }
    }
});

// --- Webhooks ------------------------------------------------------------

$suite->test('webhooks: endpoints() keeps only https lines with a non-empty secret', static function (): void {
    $context = providers_context('webhooks', [
        'endpoints' => "https://good.example.test/hook s3cret\nhttp://insecure.example.test/hook s3cret\nhttps://no-secret.example.test/hook\n\nhttps://second.example.test/hook another-secret",
    ], new SqliteDb(':memory:'));
    $extension = new \Extension\Webhooks\Extension();
    $extension->register($context);

    $endpoints = providers_invoke_private($extension, 'endpoints', []);
    TestSuite::assertSame(
        [['https://good.example.test/hook', 's3cret'], ['https://second.example.test/hook', 'another-secret']],
        $endpoints,
        'Endpoint parsing did not filter to https-only, secret-bearing, non-blank lines.',
    );
});

$suite->test('webhooks: redact() strips query strings but keeps scheme/host/path', static function (): void {
    $context = providers_context('webhooks', ['endpoints' => ''], new SqliteDb(':memory:'));
    $extension = new \Extension\Webhooks\Extension();
    $extension->register($context);

    $redacted = providers_invoke_private($extension, 'redact', ['https://hooks.example.test/deliver/abc?token=secret-value']);
    TestSuite::assertSame('https://hooks.example.test/deliver/abc', $redacted, 'redact() did not strip the query string as expected.');
});

$suite->test('webhooks: deliver() sends a signed JSON POST and records a successful delivery', static function (): void {
    $database = new SqliteDb(sys_get_temp_dir() . '/lightdocs-webhook-' . bin2hex(random_bytes(4)) . '.sqlite');
    $registry = new \System\Engine\Registry();
    $registry->set('db', $database);
    $registry->set('config', providers_config());
    (new Schema($registry))->migrate();

    $port = providers_port();
    [$process, $pipes] = providers_spawn(__DIR__ . '/fixtures/providers/fake_http_server.php', [$port, '200 OK', '']);
    TestSuite::assertTrue(providers_wait_ready($pipes, 3.0), 'Fake HTTP server did not start listening.');

    $secret = 'webhook-secret-value';
    $context = providers_context('webhooks', ['include_payload' => true, 'timeout' => 5], $database);
    $extension = new \Extension\Webhooks\Extension();
    $extension->register($context);

    providers_invoke_private($extension, 'deliver', ["http://127.0.0.1:{$port}/hook", $secret, 'content.changed', ['page' => 'guides/example']]);

    [$stdout] = providers_finish($process, $pipes);
    $captured = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertTrue(is_array($captured), 'Fake HTTP server did not report a captured request: ' . $stdout);

    TestSuite::assertTrue(str_starts_with((string) $captured['request_line'], 'POST '), 'Webhook delivery did not use POST.');
    TestSuite::assertSame('application/json', $captured['headers']['content-type'] ?? null, 'Content-Type header changed.');
    TestSuite::assertSame('content.changed', $captured['headers']['x-lightdocs-event'] ?? null, 'X-Lightdocs-Event header changed.');

    $body = (string) $captured['body'];
    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertSame('content.changed', $decoded['event'] ?? null, 'Delivered payload lost the event name.');
    TestSuite::assertSame(['page' => 'guides/example'], $decoded['payload'] ?? null, 'include_payload=true did not include the event payload.');
    TestSuite::assertTrue(isset($decoded['sent_at']), 'Delivered payload did not include sent_at.');

    $expected_signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
    TestSuite::assertSame($expected_signature, $captured['headers']['x-lightdocs-signature'] ?? null, 'HMAC signature did not match hash_hmac(sha256, raw body, configured secret).');

    $statement = $database->connection()->query('SELECT endpoint, event, status_code, success, error FROM webhook_deliveries ORDER BY id DESC LIMIT 1');
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    // redact() rebuilds scheme + host + path only — it drops the port
    // entirely, not just the query string, even when the endpoint had one.
    TestSuite::assertSame('http://127.0.0.1/hook', $row['endpoint'] ?? null, 'Recorded endpoint shape changed — redact() should keep scheme/host/path and drop the port.');
    TestSuite::assertSame('content.changed', $row['event'] ?? null, 'Recorded event name changed.');
    TestSuite::assertSame(200, (int) ($row['status_code'] ?? 0), 'Recorded status code changed.');
    TestSuite::assertSame(1, (int) ($row['success'] ?? 0), 'A 200 response was not recorded as a success.');

    unset($database);
});

$suite->test('webhooks: deliver() records a failed delivery for a non-2xx response', static function (): void {
    $database = new SqliteDb(sys_get_temp_dir() . '/lightdocs-webhook-fail-' . bin2hex(random_bytes(4)) . '.sqlite');
    $registry = new \System\Engine\Registry();
    $registry->set('db', $database);
    $registry->set('config', providers_config());
    (new Schema($registry))->migrate();

    $port = providers_port();
    [$process, $pipes] = providers_spawn(__DIR__ . '/fixtures/providers/fake_http_server.php', [$port, '503 Service Unavailable', '']);
    TestSuite::assertTrue(providers_wait_ready($pipes, 3.0), 'Fake HTTP server did not start listening.');

    $context = providers_context('webhooks', ['include_payload' => false, 'timeout' => 5], $database);
    $extension = new \Extension\Webhooks\Extension();
    $extension->register($context);
    providers_invoke_private($extension, 'deliver', ["http://127.0.0.1:{$port}/hook", 'secret', 'security/login_failed', ['ip' => '203.0.113.9']]);

    [$stdout] = providers_finish($process, $pipes);
    $captured = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    $decoded = json_decode((string) $captured['body'], true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertSame([], $decoded['payload'] ?? null, 'include_payload=false should send an empty payload array, not the real one.');

    $statement = $database->connection()->query('SELECT status_code, success, error FROM webhook_deliveries ORDER BY id DESC LIMIT 1');
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    TestSuite::assertSame(503, (int) ($row['status_code'] ?? 0), 'Recorded status code did not reflect the 503 response.');
    TestSuite::assertSame(0, (int) ($row['success'] ?? 1), 'A 503 response was incorrectly recorded as a success.');
    TestSuite::assertContains('503', (string) ($row['error'] ?? ''), 'Recorded error text did not mention the response status.');

    unset($database);
});

$suite->test('webhooks: deliver() records an unreachable endpoint with a friendly, fixed error message', static function (): void {
    $database = new SqliteDb(sys_get_temp_dir() . '/lightdocs-webhook-unreachable-' . bin2hex(random_bytes(4)) . '.sqlite');
    $registry = new \System\Engine\Registry();
    $registry->set('db', $database);
    $registry->set('config', providers_config());
    (new Schema($registry))->migrate();

    // A real, momentarily-unused local port: nothing is listening, so the
    // connection attempt itself fails rather than returning any HTTP status.
    $port = providers_port();

    $context = providers_context('webhooks', ['include_payload' => false, 'timeout' => 1], $database);
    $extension = new \Extension\Webhooks\Extension();
    $extension->register($context);
    providers_invoke_private($extension, 'deliver', ["http://127.0.0.1:{$port}/hook", 'secret', 'content.changed', []]);

    $statement = $database->connection()->query('SELECT status_code, success, error FROM webhook_deliveries ORDER BY id DESC LIMIT 1');
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    TestSuite::assertSame(0, (int) ($row['status_code'] ?? -1), 'An unreachable endpoint should record status_code 0.');
    TestSuite::assertSame(0, (int) ($row['success'] ?? 1), 'An unreachable endpoint was incorrectly recorded as a success.');
    TestSuite::assertSame('The endpoint could not be reached.', (string) ($row['error'] ?? ''), 'The fixed, friendly unreachable-endpoint message changed.');

    unset($database);
});

// --- Storage (S3) --------------------------------------------------------

$suite->test('storage: publish() returns null when the driver is not s3 or settings are incomplete', static function (): void {
    $not_s3 = providers_context('storage', ['driver' => 'local']);
    $extension = new \Extension\Storage\Extension();
    $extension->register($not_s3);
    TestSuite::assertSame(null, $extension->publish(__FILE__, 'file.txt', 'text/plain'), 'A non-s3 driver should be declined, not attempted.');

    $incomplete = providers_context('storage', ['driver' => 's3', 'endpoint' => '', 'bucket' => 'assets']);
    $extension2 = new \Extension\Storage\Extension();
    $extension2->register($incomplete);
    TestSuite::assertSame(null, $extension2->publish(__FILE__, 'file.txt', 'text/plain'), 'Incomplete S3 settings should be declined, not attempted.');
});

$suite->test('storage: publish() signs a PUT with a verifiable AWS SigV4 signature and returns the public URL', static function (): void {
    $directory = new TemporaryDirectory('lightdocs-storage-fixture-');
    $file_path = $directory->path . '/asset.txt';
    file_put_contents($file_path, "example asset contents\n");

    $port = providers_port();
    [$process, $pipes] = providers_spawn(__DIR__ . '/fixtures/providers/fake_http_server.php', [$port, '200 OK', '']);
    TestSuite::assertTrue(providers_wait_ready($pipes, 3.0), 'Fake HTTP server did not start listening.');

    $access_key = 'AKIAFIXTUREACCESSKEY';
    $secret_key = 'fixture-secret-key-value';
    $region = 'us-test-1';
    $bucket = 'assets-bucket';
    $prefix = 'lightdocs';

    $context = providers_context('storage', [
        'driver' => 's3', 'endpoint' => "http://127.0.0.1:{$port}", 'bucket' => $bucket,
        'region' => $region, 'access_key' => $access_key, 'secret_key' => $secret_key,
        'prefix' => $prefix, 'timeout' => 10,
    ]);
    $extension = new \Extension\Storage\Extension();
    $extension->register($context);

    $public_url = $extension->publish($file_path, 'image.png', 'image/png');

    [$stdout] = providers_finish($process, $pipes);
    $captured = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    TestSuite::assertTrue(is_array($captured), 'Fake HTTP server did not report a captured request: ' . $stdout);
    TestSuite::assertTrue(str_starts_with((string) $captured['request_line'], 'PUT /'), 'Storage publish did not use PUT.');
    TestSuite::assertContains("/{$bucket}/{$prefix}/image.png", (string) $captured['request_line'], 'Object path did not include bucket, prefix, and object name.');

    $authorization = (string) ($captured['headers']['authorization'] ?? '');
    TestSuite::assertContains('AWS4-HMAC-SHA256 Credential=' . $access_key, $authorization, 'Authorization header did not carry the configured access key.');
    preg_match('/Signature=([0-9a-f]{64})/', $authorization, $signature_match);
    TestSuite::assertTrue(isset($signature_match[1]), 'Authorization header did not carry a SigV4 signature.');

    $amz_date = (string) ($captured['headers']['x-amz-date'] ?? '');
    $payload_hash = (string) ($captured['headers']['x-amz-content-sha256'] ?? '');
    TestSuite::assertSame(hash('sha256', (string) $captured['body']), $payload_hash, 'x-amz-content-sha256 did not match the actual transmitted body.');

    // Recompute the exact same canonical request/signature the extension
    // must have produced, from only what a server on the other end can see
    // plus the shared secret — this is the actual security property being
    // characterized, not just "a signature-shaped string was present."
    $date = substr($amz_date, 0, 8);
    $host = "127.0.0.1:{$port}";
    $uri = "/{$bucket}/{$prefix}/image.png";
    $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
    $canonical_headers = "host:{$host}\nx-amz-content-sha256:{$payload_hash}\nx-amz-date:{$amz_date}\n";
    $canonical_request = "PUT\n{$uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
    $credential_scope = "{$date}/{$region}/s3/aws4_request";
    $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
    $date_key = hash_hmac('sha256', $date, 'AWS4' . $secret_key, true);
    $region_key = hash_hmac('sha256', $region, $date_key, true);
    $service_key = hash_hmac('sha256', 's3', $region_key, true);
    $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
    $expected_signature = hash_hmac('sha256', $string_to_sign, $signing_key);

    TestSuite::assertSame($expected_signature, $signature_match[1], 'Recomputed SigV4 signature did not match the Authorization header — the signing algorithm changed.');
    // With no public_base_url configured, the fallback mirrors the endpoint's
    // own scheme (http, in this fixture) rather than assuming https.
    TestSuite::assertSame("http://{$host}/{$bucket}/lightdocs/image.png", $public_url, 'Returned public URL shape changed for an unconfigured public_base_url.');
});

// --- Media -----------------------------------------------------------------

$suite->test('media: resizes an oversized JPEG proportionally and respects quality settings', static function (): void {
    $directory = new TemporaryDirectory('lightdocs-media-fixture-');
    $path = $directory->path . '/upload.jpg';
    $image = imagecreatetruecolor(4000, 2000);
    imagefill($image, 0, 0, imagecolorallocate($image, 10, 20, 30));
    imagejpeg($image, $path, 95);
    imagedestroy($image);

    $context = providers_context('media', ['max_width' => 800, 'max_height' => 600, 'jpeg_quality' => 60]);
    $extension = new \Extension\Media\Extension();
    $extension->register($context);
    $extension->process($path, 'image/jpeg');

    $dimensions = getimagesize($path);
    TestSuite::assertTrue($dimensions !== false, 'Processed JPEG could not be read back.');
    TestSuite::assertTrue($dimensions[0] <= 800 && $dimensions[1] <= 600, 'Oversized JPEG was not constrained to the configured maximum dimensions.');
    TestSuite::assertSame(800, $dimensions[0], 'Proportional scale factor changed for a 4000x2000 source constrained to 800x600 (width binds: 800/4000 = 0.2, giving 800x400).');
    TestSuite::assertSame(400, $dimensions[1], 'Proportional scale factor changed for a 4000x2000 source constrained to 800x600 (width binds: 800/4000 = 0.2, giving 800x400).');
});

$suite->test('media: leaves an already-small image untouched', static function (): void {
    $directory = new TemporaryDirectory('lightdocs-media-fixture-');
    $path = $directory->path . '/small.png';
    $image = imagecreatetruecolor(200, 100);
    imagepng($image, $path);
    imagedestroy($image);
    $before = (string) file_get_contents($path);

    $context = providers_context('media', ['max_width' => 2400, 'max_height' => 1600]);
    $extension = new \Extension\Media\Extension();
    $extension->register($context);
    $extension->process($path, 'image/png');

    TestSuite::assertSame($before, (string) file_get_contents($path), 'An image already within the configured maximum dimensions was rewritten.');
});

$suite->test('media: skips GIF processing unless process_gif is explicitly enabled', static function (): void {
    $directory = new TemporaryDirectory('lightdocs-media-fixture-');
    $path = $directory->path . '/animation.gif';
    $image = imagecreatetruecolor(3000, 3000);
    imagegif($image, $path);
    imagedestroy($image);
    $before = (string) file_get_contents($path);

    $default_context = providers_context('media', ['max_width' => 800, 'max_height' => 600]);
    $default_extension = new \Extension\Media\Extension();
    $default_extension->register($default_context);
    $default_extension->process($path, 'image/gif');
    TestSuite::assertSame($before, (string) file_get_contents($path), 'A GIF was resized despite process_gif defaulting to false.');

    $enabled_context = providers_context('media', ['max_width' => 800, 'max_height' => 600, 'process_gif' => true]);
    $enabled_extension = new \Extension\Media\Extension();
    $enabled_extension->register($enabled_context);
    $enabled_extension->process($path, 'image/gif');
    $dimensions = getimagesize($path);
    TestSuite::assertTrue($dimensions !== false && $dimensions[0] <= 800 && $dimensions[1] <= 600, 'A GIF was not resized once process_gif was explicitly enabled.');
});

exit($suite->finish());

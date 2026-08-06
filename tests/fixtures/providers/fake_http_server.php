<?php

declare(strict_types=1);

/**
 * Minimal single-request HTTP responder for characterizing extensions that
 * speak HTTP directly over a raw stream (Extension\Webhooks and
 * Extension\Storage both use stream_context_create()'s 'http' transport
 * rather than System\Library\Http). Accepts exactly one connection, reads
 * the request line, headers, and a Content-Length body, replies with the
 * given status/body, and prints a JSON transcript of what it received to
 * stdout.
 *
 * Invocation: php fake_http_server.php <port> <status-line> [response-body]
 */

$port = (int) ($argv[1] ?? 0);
$status = (string) ($argv[2] ?? '200 OK');
$response_body = (string) ($argv[3] ?? '');

if ($port < 1) {
    fwrite(STDERR, "A port argument is required.\n");
    exit(2);
}

$server = @stream_socket_server("tcp://127.0.0.1:{$port}", $error_code, $error_message);
if ($server === false) {
    fwrite(STDERR, "bind failed: {$error_message}\n");
    exit(2);
}
// See fake_smtp_server.php for why this goes to stderr rather than stdout.
fwrite(STDERR, "READY\n");

$connection = @stream_socket_accept($server, 5.0);
if ($connection === false) {
    fwrite(STDERR, "no connection accepted\n");
    exit(3);
}
stream_set_timeout($connection, 5);

$header_text = '';
while (!str_contains($header_text, "\r\n\r\n")) {
    $chunk = fread($connection, 8192);
    if ($chunk === false || $chunk === '') {
        break;
    }
    $header_text .= $chunk;
}

[$head, $leftover] = array_pad(explode("\r\n\r\n", $header_text, 2), 2, '');
$head_lines = explode("\r\n", $head);
$request_line = array_shift($head_lines) ?? '';

$headers = [];
foreach ($head_lines as $header_line) {
    if (!str_contains($header_line, ':')) {
        continue;
    }
    [$name, $value] = explode(':', $header_line, 2);
    $headers[strtolower(trim($name))] = trim($value);
}

$content_length = (int) ($headers['content-length'] ?? 0);
$request_body = $leftover;
while (strlen($request_body) < $content_length) {
    $chunk = fread($connection, max(1, $content_length - strlen($request_body)));
    if ($chunk === false || $chunk === '') {
        break;
    }
    $request_body .= $chunk;
}

fwrite(
    $connection,
    "HTTP/1.1 {$status}\r\nContent-Type: text/plain\r\nContent-Length: " . strlen($response_body) . "\r\nConnection: close\r\n\r\n{$response_body}"
);

fclose($connection);
fclose($server);

echo json_encode(['request_line' => $request_line, 'headers' => $headers, 'body' => $request_body], JSON_UNESCAPED_SLASHES);

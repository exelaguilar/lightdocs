<?php

declare(strict_types=1);

/**
 * Minimal scripted SMTP responder for characterizing Extension\Mail\Extension
 * over the plaintext (no STARTTLS) path. Accepts exactly one connection,
 * transcribes every line the client sends, and replies with the fixed
 * response codes the extension's expect() calls require. Exits after QUIT
 * or when the client closes the connection, printing a JSON transcript to
 * stdout.
 *
 * Invocation: php fake_smtp_server.php <port>
 */

$port = (int) ($argv[1] ?? 0);
if ($port < 1) {
    fwrite(STDERR, "A port argument is required.\n");
    exit(2);
}

$server = @stream_socket_server("tcp://127.0.0.1:{$port}", $error_code, $error_message);
if ($server === false) {
    fwrite(STDERR, "bind failed: {$error_message}\n");
    exit(2);
}
// Signals the listener is bound and about to accept — printed on stderr,
// never stdout, so the parent can tell "ready" apart from the final JSON
// transcript without consuming the one connection this fixture handles.
fwrite(STDERR, "READY\n");

$connection = @stream_socket_accept($server, 5.0);
if ($connection === false) {
    fwrite(STDERR, "no connection accepted\n");
    exit(3);
}
stream_set_timeout($connection, 5);

$write = static function (string $line) use ($connection): void {
    fwrite($connection, $line . "\r\n");
};
$read_line = static function () use ($connection): ?string {
    $line = fgets($connection, 1024);
    return $line === false ? null : rtrim($line, "\r\n");
};

$transcript = [];
$data_lines = [];
$in_data = false;
$auth_step = 0;

$write('220 fixture.local ESMTP ready');

while (true) {
    $line = $read_line();
    if ($line === null) {
        break;
    }

    if ($in_data) {
        if ($line === '.') {
            $in_data = false;
            $write('250 OK: queued');
            continue;
        }
        $data_lines[] = $line;
        continue;
    }

    $transcript[] = $line;
    $upper = strtoupper($line);

    if (str_starts_with($upper, 'EHLO')) {
        $write('250-fixture.local');
        $write('250 AUTH LOGIN');
    } elseif (str_starts_with($upper, 'AUTH LOGIN')) {
        $auth_step = 1;
        $write('334 VXNlcm5hbWU6');
    } elseif ($auth_step === 1) {
        $auth_step = 2;
        $write('334 UGFzc3dvcmQ6');
    } elseif ($auth_step === 2) {
        $auth_step = 0;
        $write('235 Authentication successful');
    } elseif (str_starts_with($upper, 'MAIL FROM')) {
        $write('250 OK');
    } elseif (str_starts_with($upper, 'RCPT TO')) {
        $write('250 OK');
    } elseif ($upper === 'DATA') {
        $write('354 Start mail input; end with <CRLF>.<CRLF>');
        $in_data = true;
    } elseif ($upper === 'QUIT') {
        $write('221 Bye');
        break;
    } else {
        $write('500 unrecognized command');
    }
}

fclose($connection);
fclose($server);

echo json_encode(['transcript' => $transcript, 'data' => $data_lines], JSON_UNESCAPED_SLASHES);

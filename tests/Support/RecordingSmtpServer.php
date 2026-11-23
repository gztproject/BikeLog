<?php

/**
 * Local SMTP listener used only by tests. It speaks the greeting, EHLO, MAIL,
 * RCPT, and DATA sequence that Symfony's EsmtpTransport sends, and records
 * each completed message. It never relays.
 *
 * Arguments: <address-file> <log-file> <stop-file>
 */
if (PHP_SAPI !== 'cli' || !isset($argv[0]) || realpath($argv[0]) !== realpath(__FILE__)) {
    return;
}

$addressFile = $argv[1] ?? '';
$logFile = $argv[2] ?? '';
$stopFile = $argv[3] ?? '';

if ($addressFile === '' || $logFile === '' || $stopFile === '') {
    fwrite(STDERR, "usage: RecordingSmtpServer.php <address-file> <log-file> <stop-file>\n");
    exit(1);
}

$listen = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($listen === false) {
    fwrite(STDERR, "listen failed: $errstr ($errno)\n");
    exit(1);
}

$address = stream_socket_get_name($listen, false);
file_put_contents($addressFile, $address);
stream_set_blocking($listen, false);

$messages = [];
$deadline = time() + 60;

while (time() < $deadline && !is_file($stopFile)) {
    $connection = @stream_socket_accept($listen, 1);
    if ($connection === false) {
        continue;
    }

    stream_set_timeout($connection, 5);
    fwrite($connection, "220 localhost ESMTP test\r\n");

    $collecting = false;
    $body = '';

    while (!feof($connection)) {
        $line = fgets($connection);
        if ($line === false) {
            break;
        }

        if ($collecting) {
            if ($line === ".\r\n") {
                $collecting = false;
                $messages[] = $body;
                $body = '';
                file_put_contents($logFile, (string) count($messages));
                fwrite($connection, "250 queued\r\n");
            } else {
                $body .= $line;
            }
            continue;
        }

        $command = strtoupper(substr($line, 0, 4));
        if (str_starts_with($command, 'EHLO') || str_starts_with($command, 'HELO')) {
            fwrite($connection, "250-localhost\r\n250 HELP\r\n");
        } elseif (str_starts_with($command, 'MAIL') || str_starts_with($command, 'RCPT') || str_starts_with($command, 'RSET') || str_starts_with($command, 'NOOP')) {
            fwrite($connection, "250 OK\r\n");
        } elseif (str_starts_with($command, 'DATA')) {
            $collecting = true;
            fwrite($connection, "354 Start mail input\r\n");
        } elseif (str_starts_with($command, 'QUIT')) {
            fwrite($connection, "221 bye\r\n");
            break;
        } else {
            fwrite($connection, "502 unimplemented\r\n");
        }
    }

    fclose($connection);
}

fclose($listen);
exit(0);

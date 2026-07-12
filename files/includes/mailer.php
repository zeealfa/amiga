<?php
// Minimal native SMTP client (SMTPS + AUTH LOGIN) — no third-party
// libraries, per the project's vanilla-PHP-only constraint. Talks to
// GoDaddy's mailbox SMTP server directly over stream_socket_client().

require_once __DIR__ . '/mail_config.php';

function smtp_send_mail(string $to, string $subject, string $body): array
{
    $log = '';
    $read = function ($socket) use (&$log) {
        $line = fgets($socket, 515);
        $log .= (string) $line;
        return $line;
    };
    $write = function ($socket, string $data) use (&$log) {
        $log .= '> ' . $data . "\n";
        fwrite($socket, $data . "\r\n");
    };

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client(
        'ssl://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        15,
        STREAM_CLIENT_CONNECT
    );
    if (!$socket) {
        return ['success' => false, 'error' => "Connection failed: $errstr ($errno)", 'log' => $log];
    }

    do {
        $response = $read($socket);
    } while (isset($response[3]) && $response[3] === '-');
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        return ['success' => false, 'error' => "Unexpected greeting: $response", 'log' => $log];
    }

    $write($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    do {
        $response = $read($socket);
    } while (isset($response[3]) && $response[3] === '-');
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "EHLO failed: $response", 'log' => $log];
    }

    $write($socket, 'AUTH LOGIN');
    $response = $read($socket);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => "AUTH LOGIN failed: $response", 'log' => $log];
    }

    $write($socket, base64_encode(SMTP_USER));
    $response = $read($socket);
    if (substr($response, 0, 3) !== '334') {
        fclose($socket);
        return ['success' => false, 'error' => "Username rejected: $response", 'log' => $log];
    }

    $write($socket, base64_encode(SMTP_PASS));
    $response = $read($socket);
    if (substr($response, 0, 3) !== '235') {
        fclose($socket);
        return ['success' => false, 'error' => "Authentication failed: $response", 'log' => $log];
    }

    $write($socket, 'MAIL FROM:<' . MAIL_FROM . '>');
    $response = $read($socket);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "MAIL FROM failed: $response", 'log' => $log];
    }

    $write($socket, 'RCPT TO:<' . $to . '>');
    $response = $read($socket);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "RCPT TO failed: $response", 'log' => $log];
    }

    $write($socket, 'DATA');
    $response = $read($socket);
    if (substr($response, 0, 3) !== '354') {
        fclose($socket);
        return ['success' => false, 'error' => "DATA failed: $response", 'log' => $log];
    }

    $headers = "From: AmigaSource <" . MAIL_FROM . ">\r\n"
        . "To: <" . $to . ">\r\n"
        . "Subject: " . $subject . "\r\n"
        . "Date: " . date('r') . "\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    // Dot-stuffing per RFC 5321 4.5.2 — a line starting with '.' would
    // otherwise be read by the server as the end-of-DATA marker.
    $escaped_body = preg_replace('/^\./m', '..', $body);

    fwrite($socket, $headers . "\r\n" . $escaped_body . "\r\n.\r\n");
    $response = $read($socket);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        return ['success' => false, 'error' => "Message not accepted: $response", 'log' => $log];
    }

    $write($socket, 'QUIT');
    fclose($socket);

    return ['success' => true, 'error' => null, 'log' => $log];
}

function notify_admin_new_submission(string $type, string $summary): array
{
    $subject = 'AmigaSource: new ' . $type . ' submission awaiting review';
    $body = "A new $type submission was just made and is waiting for admin review:\n\n"
        . $summary . "\n\n"
        . "Review it at: https://" . ($_SERVER['SERVER_NAME'] ?? 'testamigasource.com') . "/admin/submissions.php\n\n"
        . "---\n"
        . "Note: this notification was sent to links@testamigasource.com. "
        . "If this inbox isn't actively monitored, submissions may sit in the "
        . "queue unnoticed — check periodically or forward this address "
        . "elsewhere.\n";

    return smtp_send_mail(MAIL_TO, $subject, $body);
}

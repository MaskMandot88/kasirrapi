<?php
// includes/mailer.php
// SMTP sender ringan tanpa composer/PHPMailer.
// Cocok untuk cPanel yang menyediakan SMTP SSL port 465.

require_once __DIR__ . '/../config/mail.php';

if (!function_exists('smtp_read_response')) {
    function smtp_read_response($socket) {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return $response;
    }
}

if (!function_exists('smtp_expect')) {
    function smtp_expect($socket, $codes) {
        $response = smtp_read_response($socket);
        $code = (int) substr($response, 0, 3);

        if (!in_array($code, (array)$codes, true)) {
            throw new Exception('SMTP error: ' . trim($response));
        }

        return $response;
    }
}

if (!function_exists('smtp_command')) {
    function smtp_command($socket, $command, $expectCodes) {
        fwrite($socket, $command . "\r\n");
        return smtp_expect($socket, $expectCodes);
    }
}

if (!function_exists('smtp_encode_header')) {
    function smtp_encode_header($text) {
        $text = (string)$text;

        if (preg_match('/[^\x20-\x7E]/', $text)) {
            return '=?UTF-8?B?' . base64_encode($text) . '?=';
        }

        return $text;
    }
}

if (!function_exists('smtp_normalize_email')) {
    function smtp_normalize_email($email) {
        return trim((string)$email);
    }
}

if (!function_exists('smtp_dot_stuff')) {
    function smtp_dot_stuff($message) {
        $message = str_replace(["\r\n", "\r"], "\n", $message);
        $lines = explode("\n", $message);

        foreach ($lines as &$line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
        }

        return implode("\r\n", $lines);
    }
}

if (!function_exists('send_smtp_mail')) {
    function send_smtp_mail($toEmail, $subject, $textBody, $htmlBody = '') {
        $toEmail = smtp_normalize_email($toEmail);

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email tujuan tidak valid.');
        }

        $host = SMTP_HOST;
        $port = (int) SMTP_PORT;
        $secure = strtolower((string) SMTP_SECURE);

        $fromEmail = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;

        if ($password === '' || $password === 'ISI_PASSWORD_EMAIL_CPANEL_DI_SINI') {
            throw new Exception('Password SMTP belum diisi di config/mail.php.');
        }

        $remote = ($secure === 'ssl')
            ? 'ssl://' . $host . ':' . $port
            : $host . ':' . $port;

        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new Exception('Tidak bisa konek ke SMTP: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_timeout($socket, 30);

        try {
            smtp_expect($socket, [220]);

            $serverName = $_SERVER['HTTP_HOST'] ?? 'localhost';

            smtp_command($socket, 'EHLO ' . $serverName, [250]);

            if ($secure === 'tls') {
                smtp_command($socket, 'STARTTLS', [220]);

                $cryptoOk = stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                );

                if (!$cryptoOk) {
                    throw new Exception('Gagal mengaktifkan TLS SMTP.');
                }

                smtp_command($socket, 'EHLO ' . $serverName, [250]);
            }

            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);

            smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            smtp_command($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
            smtp_command($socket, 'DATA', [354]);

            $boundary = 'kasirrapi_' . bin2hex(random_bytes(12));
            $encodedSubject = smtp_encode_header($subject);
            $encodedFromName = smtp_encode_header($fromName);

            $headers = [];
            $headers[] = 'Date: ' . date('r');
            $headers[] = 'From: ' . $encodedFromName . ' <' . $fromEmail . '>';
            $headers[] = 'To: <' . $toEmail . '>';
            $headers[] = 'Subject: ' . $encodedSubject;
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $headers[] = 'X-Mailer: KasirRapi SMTP Mailer';

            $textBody = (string)$textBody;
            $htmlBody = trim((string)$htmlBody);

            if ($htmlBody === '') {
                $htmlBody = nl2br(htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8'));
            }

            $message = implode("\r\n", $headers);
            $message .= "\r\n\r\n";

            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $textBody . "\r\n\r\n";

            $message .= '--' . $boundary . "\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
            $message .= $htmlBody . "\r\n\r\n";

            $message .= '--' . $boundary . "--";

            fwrite($socket, smtp_dot_stuff($message) . "\r\n.\r\n");
            smtp_expect($socket, [250]);

            smtp_command($socket, 'QUIT', [221]);

            fclose($socket);

            return true;
        } catch (Throwable $e) {
            @fwrite($socket, "QUIT\r\n");
            @fclose($socket);
            throw $e;
        }
    }
}

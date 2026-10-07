<?php
/**
 * mailer.php - minimal SMTP client (AUTH LOGIN, STARTTLS / SSL). SMTP details come from Admin > Settings.
 *   mail_configured();   mail_send($to, $subject, $html, $text) -> throws RuntimeException on failure
 */
function mail_configured(): bool
{
    return cfg('smtp_host') !== '' && cfg('smtp_from_email') !== '';
}

function mail_clean(string $s): string { return trim(str_replace(["\r", "\n", "\0"], ' ', $s)); }

function mail_send(string $to, string $subject, string $html, string $text = ''): void
{
    $to = mail_clean($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid recipient email');
    if (!mail_configured()) throw new RuntimeException('SMTP settings poore nahi hain (Admin > Settings > Email).');

    $host   = cfg('smtp_host');
    $secure = cfg('smtp_secure', 'tls');                 // none | tls (STARTTLS) | ssl
    $port   = (int) (cfg('smtp_port') ?: ($secure === 'ssl' ? 465 : 587));
    $user   = cfg('smtp_user');
    $pass   = cfg('smtp_pass');
    $from   = mail_clean(cfg('smtp_from_email'));
    $fname  = mail_clean(cfg('smtp_from_name') ?: setting('site_name_en'));

    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $en, $es, 12, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) throw new RuntimeException("SMTP server se connect nahi hua ($host:$port): $es");
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): array {
        $all = ''; $code = 0;
        while (($line = fgets($fp, 1024)) !== false) {
            $all .= $line; $code = (int) substr($line, 0, 3);
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return [$code, trim($all)];
    };
    $cmd = function (string $c, array $ok) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        [$code, $msg] = $read();
        if (!in_array($code, $ok, true)) throw new RuntimeException('SMTP error: ' . ($code ? $msg : 'no response'));
        return $msg;
    };

    try {
        [$code, $msg] = $read();
        if ($code !== 220) throw new RuntimeException('SMTP greeting fail: ' . $msg);
        $me = preg_replace('/[^a-z0-9.\-]/i', '', parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost') ?: 'localhost';
        $cmd("EHLO $me", [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('STARTTLS fail');
            $cmd("EHLO $me", [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd("MAIL FROM:<$from>", [250]);
        $cmd("RCPT TO:<$to>", [250, 251]);
        $cmd('DATA', [354]);

        $b = 'b' . bin2hex(random_bytes(8));
        $enc = fn(string $s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $text = $text !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $msg  = 'Date: ' . date('r') . "\r\n"
              . 'From: ' . $enc($fname) . " <$from>\r\n"
              . "To: <$to>\r\n"
              . 'Subject: ' . $enc(mail_clean($subject)) . "\r\n"
              . 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $me . ">\r\n"
              . "MIME-Version: 1.0\r\n"
              . "Content-Type: multipart/alternative; boundary=\"$b\"\r\n\r\n"
              . "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) . "\r\n"
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n"
              . "--$b--\r\n";
        // base64 bodies contain no leading dots, so no dot-stuffing needed
        fwrite($fp, $msg . "\r\n.\r\n");
        [$code, $resp] = $read();
        if ($code !== 250) throw new RuntimeException('SMTP ne mail accept nahi ki: ' . $resp);
        @fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

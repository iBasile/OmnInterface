<?php

/**
 * Envoi via le transport configuré dans config.php.
 */
class Mailer
{
    public static function sendVerificationCode(string $toEmail, string $code): bool
    {
        $subject = '[' . APP_NAME . '] Votre code de vérification';
        $body = self::buildTemplate($code);

        return self::send($toEmail, $subject, $body);
    }

    private static function buildTemplate(string $code): string
    {
        $appName = h(APP_NAME);
        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body style="margin:0;padding:0;background:#f2f2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
  <table role="presentation" width="100%" style="padding:32px 0;">
    <tr><td align="center">
      <table role="presentation" width="420" style="background:#ffffff;border-radius:20px;padding:36px;">
        <tr><td style="text-align:center;">
          <div style="font-size:20px;font-weight:600;color:#1d1d1f;margin-bottom:4px;">{$appName}</div>
          <div style="font-size:14px;color:#6e6e73;margin-bottom:28px;">Vérification de votre adresse e-mail</div>
          <div style="font-size:36px;font-weight:700;letter-spacing:0.15em;color:#0071e3;background:#f2f2f7;border-radius:14px;padding:18px 0;margin-bottom:20px;">{$code}</div>
          <div style="font-size:13px;color:#86868b;line-height:1.5;">Ce code expire dans 10 minutes.<br>Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</div>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private static function send(string $to, string $subject, string $htmlBody): bool
    {
        if (MAIL_TRANSPORT === 'smtp') {
            return self::sendSmtp($to, $subject, $htmlBody);
        }
        if (MAIL_TRANSPORT !== 'mail') {
            throw new RuntimeException('MAIL_TRANSPORT doit être "mail" ou "smtp".');
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
            'Reply-To: ' . MAIL_FROM,
            'X-Mailer: PHP/' . phpversion(),
        ];

        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $to, string $subject, string $htmlBody): bool
    {
        if (!in_array(SMTP_ENCRYPTION, ['tls', 'ssl', 'none'], true)) {
            throw new RuntimeException('SMTP_ENCRYPTION doit être "tls", "ssl" ou "none".');
        }
        if (SMTP_ENCRYPTION === 'tls' && SMTP_PORT === 465) {
            throw new RuntimeException('Le port SMTP 465 utilise SSL direct. Utilisez SMTP_ENCRYPTION = "ssl", ou le port 587 avec "tls".');
        }
        $remote = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
        $socket = fsockopen($remote, SMTP_PORT, $errno, $error, SMTP_TIMEOUT);
        if (!$socket) {
            throw new RuntimeException('Connexion SMTP impossible : ' . $error);
        }
        stream_set_timeout($socket, SMTP_TIMEOUT);
        try {
            self::expect($socket, 220);
            self::command($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
            if (SMTP_ENCRYPTION === 'tls') {
                self::command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Impossible d’activer TLS pour le serveur SMTP.');
                }
                self::command($socket, 'EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), 250);
            }
            if (SMTP_AUTH) {
                self::command($socket, 'AUTH LOGIN', 334);
                self::command($socket, base64_encode(SMTP_USERNAME), 334);
                self::command($socket, base64_encode(SMTP_PASSWORD), 235);
            }
            self::command($socket, 'MAIL FROM:<' . MAIL_FROM . '>', 250);
            self::command($socket, 'RCPT TO:<' . $to . '>', 250);
            self::command($socket, 'DATA', 354);
            $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n"
                . "Reply-To: " . MAIL_FROM . "\r\nMIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $message = str_replace("\n.", "\n..", str_replace("\r\n", "\n", $htmlBody));
            fwrite($socket, $headers . "\r\n" . str_replace("\n", "\r\n", $message) . "\r\n.\r\n");
            self::expect($socket, 250);
            fwrite($socket, "QUIT\r\n");
            return true;
        } finally {
            fclose($socket);
        }
    }

    private static function command($socket, string $command, int $expected): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $expected);
    }

    private static function expect($socket, int $expected): void
    {
        $response = '';
        do {
            $line = fgets($socket);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                $reason = !empty($meta['timed_out']) ? 'délai d’attente dépassé' : 'connexion fermée par le serveur';
                throw new RuntimeException('Réponse SMTP incomplète (' . $reason . '). Vérifiez SMTP_HOST, SMTP_PORT et SMTP_ENCRYPTION.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        if ((int) substr($response, 0, 3) !== $expected) {
            throw new RuntimeException('Erreur SMTP : ' . trim($response));
        }
    }
}

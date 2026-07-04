<?php
/**
 * Minimaler SMTP-Client für den Versand über einen externen SMTP-Server.
 * Unterstützt: Klartext, STARTTLS, implizites SSL, AUTH LOGIN.
 * Kein Composer/PHPMailer nötig - spricht das SMTP-Protokoll direkt über einen Socket.
 */
class SmtpMailer
{
    private $socket;
    private array $log = [];

    /**
     * @return array{ok: bool, error?: string, log?: string[]}
     */
    public static function send(array $smtpConfig, string $to, string $subject, string $body): array
    {
        $mailer = new self();
        try {
            $mailer->connect($smtpConfig['host'], (int)$smtpConfig['port'], $smtpConfig['encryption'] ?? 'tls');
            $mailer->expect(220);

            $mailer->command('EHLO ' . (gethostname() ?: 'slideforge'));
            $mailer->expect(250);

            if (($smtpConfig['encryption'] ?? '') === 'tls') {
                $mailer->command('STARTTLS');
                $mailer->expect(220);
                if (!stream_socket_enable_crypto($mailer->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS fehlgeschlagen (TLS-Verhandlung nicht möglich).');
                }
                $mailer->command('EHLO ' . (gethostname() ?: 'slideforge'));
                $mailer->expect(250);
            }

            if (!empty($smtpConfig['username'])) {
                $mailer->command('AUTH LOGIN');
                $mailer->expect(334);
                $mailer->command(base64_encode($smtpConfig['username']));
                $mailer->expect(334);
                $mailer->command(base64_encode($smtpConfig['password'] ?? ''));
                $mailer->expect(235);
            }

            $fromEmail = $smtpConfig['from_email'] ?: $smtpConfig['username'];
            $fromName = $smtpConfig['from_name'] ?? APP_NAME;

            $mailer->command('MAIL FROM:<' . $fromEmail . '>');
            $mailer->expect(250);
            $mailer->command('RCPT TO:<' . $to . '>');
            $mailer->expect(250, 251);
            $mailer->command('DATA');
            $mailer->expect(354);

            $headers = [
                'From: ' . self::encodeHeader($fromName) . ' <' . $fromEmail . '>',
                'To: <' . $to . '>',
                'Subject: ' . self::encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Date: ' . date('r'),
            ];
            $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.";
            $mailer->command($data);
            $mailer->expect(250);

            $mailer->command('QUIT');
            $mailer->close();

            return ['ok' => true, 'log' => $mailer->log];
        } catch (Throwable $e) {
            $mailer->close();
            return ['ok' => false, 'error' => $e->getMessage(), 'log' => $mailer->log];
        }
    }

    private function connect(string $host, int $port, string $encryption): void
    {
        if ($host === '') {
            throw new RuntimeException('Kein SMTP-Server hinterlegt.');
        }
        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create();
        $this->socket = @stream_socket_client($target, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) {
            throw new RuntimeException("Verbindung zu $host:$port fehlgeschlagen: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 10);
    }

    private function command(string $cmd): void
    {
        $this->log[] = '> ' . (str_starts_with($cmd, 'AUTH') || strlen($cmd) > 200 ? '[...]' : $cmd);
        fwrite($this->socket, $cmd . "\r\n");
    }

    private function expect(int ...$codes): void
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            // Letzte Zeile einer SMTP-Antwort hat ein Leerzeichen nach dem Code (kein '-')
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = '< ' . trim($response);
        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException("Unerwartete SMTP-Antwort: " . trim($response));
        }
    }

    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }

    private static function encodeHeader(string $s): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }
}

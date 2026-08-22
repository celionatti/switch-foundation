<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Transport;

use RuntimeException;
use Switch\Foundation\Mailer\Mailable;

class SmtpTransport implements TransportInterface
{
    private string $host;
    private int $port;
    private ?string $username;
    private ?string $password;
    private string $encryption;
    private int $timeout;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 25,
        ?string $username = null,
        ?string $password = null,
        string $encryption = 'tls',
        int $timeout = 10
    ) {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = strtolower($encryption);
        $this->timeout = $timeout;
    }

    public function send(Mailable $mailable): bool
    {
        $protocol = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($protocol . $this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$socket) {
            throw new RuntimeException("Could not connect to SMTP server {$this->host}:{$this->port} - {$errstr} ({$errno})");
        }

        $this->readResponse($socket);

        // EHLO handshake
        $this->sendCommand($socket, "EHLO " . gethostname());

        // STARTTLS
        if ($this->encryption === 'tls') {
            $this->sendCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException("Failed to negotiate TLS handshake with SMTP host.");
            }
            $this->sendCommand($socket, "EHLO " . gethostname());
        }

        // Authentication (AUTH LOGIN)
        if ($this->username !== null && $this->password !== null) {
            $this->sendCommand($socket, "AUTH LOGIN");
            $this->sendCommand($socket, base64_encode($this->username));
            $this->sendCommand($socket, base64_encode($this->password));
        }

        // MAIL FROM
        $fromList = $mailable->getFrom();
        $fromEmail = !empty($fromList) ? $fromList[0]['email'] : 'noreply@localhost';
        $this->sendCommand($socket, "MAIL FROM:<{$fromEmail}>");

        // RCPT TO
        $allRecipients = array_merge($mailable->getTo(), $mailable->getCc(), $mailable->getBcc());
        foreach ($allRecipients as $rcpt) {
            $this->sendCommand($socket, "RCPT TO:<{$rcpt['email']}>");
        }

        // DATA
        $this->sendCommand($socket, "DATA");
        $raw = $mailable->renderRaw();
        fwrite($socket, $raw . "\r\n.\r\n");
        $this->readResponse($socket);

        // QUIT
        $this->sendCommand($socket, "QUIT");
        fclose($socket);

        return true;
    }

    private function sendCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
}

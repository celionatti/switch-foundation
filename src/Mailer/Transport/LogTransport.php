<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Transport;

use Switch\Foundation\Mailer\Mailable;

class LogTransport implements TransportInterface
{
    private string $logPath;

    public function __construct(string $logPath = 'storage/logs/mail.log')
    {
        $this->logPath = $logPath;
    }

    public function send(Mailable $mailable): bool
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $entry = "==================== [" . date('Y-m-d H:i:s') . "] ====================\n";
        $entry .= $mailable->renderRaw() . "\n\n";

        return @file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}

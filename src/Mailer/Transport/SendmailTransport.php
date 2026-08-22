<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Transport;

use Switch\Foundation\Mailer\Mailable;

class SendmailTransport implements TransportInterface
{
    private string $command;

    public function __construct(string $command = '/usr/sbin/sendmail -bs')
    {
        $this->command = $command;
    }

    public function send(Mailable $mailable): bool
    {
        $process = proc_open($this->command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            return false;
        }

        fwrite($pipes[0], $mailable->renderRaw());
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }
}

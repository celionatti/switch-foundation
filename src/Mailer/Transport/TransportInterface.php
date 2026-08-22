<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Transport;

use Switch\Foundation\Mailer\Mailable;

interface TransportInterface
{
    public function send(Mailable $mailable): bool;
}

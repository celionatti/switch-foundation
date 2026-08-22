<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Channel;

use Switch\Foundation\Notification\Notification;

interface ChannelInterface
{
    public function send(mixed $notifiable, Notification $notification): void;
}

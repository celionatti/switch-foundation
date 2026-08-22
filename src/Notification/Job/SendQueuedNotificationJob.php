<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Job;

use Switch\Foundation\Notification\Notification;
use Switch\Foundation\Notification\NotificationManager;
use Switch\Foundation\Queue\Job;

class SendQueuedNotificationJob extends Job
{
    public mixed $notifiables;
    public Notification $notification;
    public ?array $channels;

    public function __construct(mixed $notifiables, Notification $notification, ?array $channels = null)
    {
        $this->notifiables = $notifiables;
        $this->notification = $notification;
        $this->channels = $channels;
        $this->queue = $notification->queueName;
        $this->delay = $notification->queueDelay;
        $this->tries = $notification->queueTries;
    }

    public function handle(): void
    {
        NotificationManager::getInstance()->sendNow($this->notifiables, $this->notification, $this->channels);
    }
}

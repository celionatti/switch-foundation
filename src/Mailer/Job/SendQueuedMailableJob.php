<?php

declare(strict_types=1);

namespace Switch\Foundation\Mailer\Job;

use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Queue\Job;

class SendQueuedMailableJob extends Job
{
    public Mailable $mailable;

    public function __construct(Mailable $mailable)
    {
        $this->mailable = $mailable;
        $this->queue = $mailable->queueName;
        $this->delay = $mailable->queueDelay;
        $this->tries = $mailable->queueTries;
    }

    public function handle(): void
    {
        MailManager::getInstance()->sendNow($this->mailable);
    }
}

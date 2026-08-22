<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification;

use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Queue\ShouldQueue;

abstract class Notification
{
    public string $id;
    public string $queueName = 'default';
    public int $queueDelay = 0;
    public int $queueTries = 3;

    public function __construct()
    {
        $this->id = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string> E.g. ['database', 'mail', 'broadcast', 'sse']
     */
    abstract public function via(mixed $notifiable): array;

    /**
     * Get the array representation for database persistence.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): Mailable|string
    {
        $mailable = new Mailable();
        $mailable->subject('Notification from ' . (get_class($this)))
                 ->text(json_encode($this->toArray($notifiable)));
        return $mailable;
    }

    /**
     * Get the real-time broadcast representation.
     *
     * @return array<string, mixed>
     */
    public function toBroadcast(mixed $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get the SSE real-time representation.
     *
     * @return array<string, mixed>
     */
    public function toSse(mixed $notifiable): array
    {
        return $this->toBroadcast($notifiable);
    }

    /**
     * Get the default array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [];
    }

    public function onQueue(string $queue): static
    {
        $this->queueName = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->queueDelay = $seconds;
        return $this;
    }

    public function tries(int $tries): static
    {
        $this->queueTries = $tries;
        return $this;
    }

    public function shouldQueue(): bool
    {
        return $this instanceof ShouldQueue;
    }
}

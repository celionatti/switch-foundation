<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification;

use InvalidArgumentException;
use Switch\Foundation\Notification\Channel\BroadcastChannel;
use Switch\Foundation\Notification\Channel\ChannelInterface;
use Switch\Foundation\Notification\Channel\DatabaseChannel;
use Switch\Foundation\Notification\Channel\MailChannel;
use Switch\Foundation\Notification\Channel\SseChannel;
use Switch\Foundation\Notification\Job\SendQueuedNotificationJob;
use Switch\Foundation\Queue\Facade\Queue;
use Traversable;

class NotificationManager
{
    private static ?self $instance = null;
    private array $channels = [];
    private array $customDrivers = [];

    public function __construct()
    {
        $this->channels['database'] = new DatabaseChannel();
        $this->channels['mail'] = new MailChannel();
        $this->channels['broadcast'] = new BroadcastChannel();
        $this->channels['sse'] = new SseChannel();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function channel(string $name): ChannelInterface
    {
        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        if (isset($this->customDrivers[$name])) {
            return $this->channels[$name] = ($this->customDrivers[$name])($this);
        }

        throw new InvalidArgumentException("Notification channel [{$name}] is not defined.");
    }

    public function setChannel(string $name, ChannelInterface $channel): static
    {
        $this->channels[$name] = $channel;
        return $this;
    }

    public function extend(string $channel, callable $callback): static
    {
        $this->customDrivers[$channel] = $callback;
        return $this;
    }

    public function route(string $channel, mixed $route): AnonymousNotifiable
    {
        return (new AnonymousNotifiable())->route($channel, $route);
    }

    /**
     * Send the given notification to the given notifiables (auto-queues if ShouldQueue).
     */
    public function send(mixed $notifiables, Notification $notification, ?array $channels = null): void
    {
        if ($notification->shouldQueue()) {
            $this->queueNotification($notifiables, $notification, $channels);
            return;
        }

        $this->sendNow($notifiables, $notification, $channels);
    }

    /**
     * Send notification immediately through specified or default channels.
     */
    public function sendNow(mixed $notifiables, Notification $notification, ?array $channels = null): void
    {
        $list = $this->formatNotifiables($notifiables);

        foreach ($list as $notifiable) {
            $viaChannels = $channels ?? $notification->via($notifiable);

            foreach ($viaChannels as $channelName) {
                $driver = $this->channel($channelName);
                $driver->send($notifiable, $notification);
            }
        }
    }

    /**
     * Queue the notification for asynchronous background sending.
     */
    public function queueNotification(mixed $notifiables, Notification $notification, ?array $channels = null): string|int
    {
        $job = new SendQueuedNotificationJob($notifiables, $notification, $channels);
        return Queue::push($job, $notification->queueName);
    }

    protected function formatNotifiables(mixed $notifiables): array
    {
        if ($notifiables instanceof Traversable) {
            return iterator_to_array($notifiables);
        }

        return is_array($notifiables) ? $notifiables : [$notifiables];
    }
}

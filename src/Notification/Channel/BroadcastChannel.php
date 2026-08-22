<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Channel;

use Switch\Foundation\Notification\Notification;
use Switch\Live\LiveResponse;

class BroadcastChannel implements ChannelInterface
{
    private static array $broadcastEvents = [];

    public function send(mixed $notifiable, Notification $notification): void
    {
        $payload = $notification->toBroadcast($notifiable);
        $payload['id'] = $notification->id;
        $payload['type'] = get_class($notification);

        self::$broadcastEvents[] = [
            'notifiable' => $notifiable,
            'notification' => $notification,
            'payload' => $payload,
        ];

        // If Switch Live SPA is installed, emit toast
        if (class_exists(LiveResponse::class) && !empty($payload['message'])) {
            try {
                LiveResponse::toast(
                    (string) $payload['message'],
                    (string) ($payload['title'] ?? 'Notification'),
                    (string) ($payload['level'] ?? 'info')
                );
            } catch (\Throwable) {
                // Ignore if not in live context
            }
        }
    }

    public static function events(): array
    {
        return self::$broadcastEvents;
    }

    public static function flush(): void
    {
        self::$broadcastEvents = [];
    }
}

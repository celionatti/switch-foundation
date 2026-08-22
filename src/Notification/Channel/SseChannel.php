<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Channel;

use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Notification\Notification;

class SseChannel implements ChannelInterface
{
    private CacheManager $cache;

    public function __construct(?CacheManager $cache = null)
    {
        $this->cache = $cache ?? CacheManager::getInstance();
    }

    public function send(mixed $notifiable, Notification $notification): void
    {
        $id = method_exists($notifiable, 'getAuthIdentifier')
            ? $notifiable->getAuthIdentifier()
            : ($notifiable->id ?? 'global');

        $key = 'sse_stream:' . $id;

        $payload = $notification->toSse($notifiable);
        $payload['id'] = $notification->id;
        $payload['type'] = get_class($notification);
        $payload['timestamp'] = time();

        $existing = (array) $this->cache->get($key, []);
        $existing[] = $payload;

        // Keep last 50 events in stream buffer for 10 minutes
        if (count($existing) > 50) {
            $existing = array_slice($existing, -50);
        }

        $this->cache->put($key, $existing, 600);
    }
}

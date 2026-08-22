<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Realtime;

use Switch\Foundation\Cache\CacheManager;

class NotificationStream
{
    private CacheManager $cache;

    public function __construct(?CacheManager $cache = null)
    {
        $this->cache = $cache ?? CacheManager::getInstance();
    }

    /**
     * Start the real-time Server-Sent Events (SSE) streaming loop.
     */
    public function stream(mixed $userId = 'global', int $maxSeconds = 30, int $sleepMs = 500000): void
    {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $key = 'sse_stream:' . $userId;
        $startTime = time();
        $lastSentTime = time();

        echo "retry: 3000\n\n";
        echo "event: connected\ndata: " . json_encode(['status' => 'connected', 'user_id' => $userId]) . "\n\n";
        flush();

        while (time() - $startTime < $maxSeconds) {
            if (connection_aborted()) {
                break;
            }

            $events = (array) $this->cache->get($key, []);
            if (!empty($events)) {
                foreach ($events as $event) {
                    echo "id: {$event['id']}\n";
                    echo "event: notification\n";
                    echo "data: " . json_encode($event) . "\n\n";
                }
                $this->cache->forget($key);
                flush();
            } else {
                // Send heartbeat every 15 seconds to prevent proxy timeout
                if (time() - $lastSentTime >= 15) {
                    echo ": heartbeat " . time() . "\n\n";
                    $lastSentTime = time();
                    flush();
                }
            }

            usleep($sleepMs);
        }
    }

    /**
     * Generate zero-config client-side JavaScript for listening to real-time notifications.
     */
    public static function renderScript(string $streamUrl = '/api/notifications/stream'): string
    {
        return <<<HTML
<script>
(function() {
    if (!('EventSource' in window)) return;
    
    const source = new EventSource('{$streamUrl}');
    
    source.addEventListener('notification', function(e) {
        try {
            const data = JSON.parse(e.data);
            window.dispatchEvent(new CustomEvent('switch:notification', { detail: data }));
            
            // If Switch Live Toast is available, display automatically
            if (window.SwitchLive && typeof window.SwitchLive.showToast === 'function') {
                window.SwitchLive.showToast(data.message || data.title || 'New Notification', data.level || 'info');
            } else if (typeof window.showToast === 'function') {
                window.showToast(data.message || data.title || 'New Notification', data.level || 'info');
            }
            
            // Browser Web Notification API support
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(data.title || 'Notification', {
                    body: data.message || '',
                    icon: data.icon || '/favicon.ico'
                });
            }
        } catch (err) {
            console.error('[Switch Notification Stream] Parse error:', err);
        }
    });

    source.onerror = function() {
        // EventSource will automatically attempt reconnection
    };
})();
</script>
HTML;
    }
}

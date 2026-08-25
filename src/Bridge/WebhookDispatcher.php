<?php

declare(strict_types=1);

namespace Switch\Foundation\Bridge;

use Switch\Foundation\Queue\Facade\Queue;

class WebhookDispatcher
{
    /**
     * @var array<int, array<string, mixed>> Delivery history log
     */
    public static array $deliveryHistory = [];

    /**
     * Dispatch an outbound webhook via background queue.
     *
     * @param string $url Target Webhook Endpoint URL
     * @param array<string, mixed> $payload Event data payload
     * @param string $secret Shared HMAC Secret Key for signing
     * @param array<string, string> $headers Custom headers
     * @param array<string, mixed> $options Queue, attempts, and backoff settings
     * @return WebhookJob
     */
    public static function dispatch(
        string $url,
        array $payload,
        string $secret = '',
        array $headers = [],
        array $options = []
    ): WebhookJob {
        $queue = $options['queue'] ?? 'webhooks';
        $maxAttempts = (int) ($options['max_attempts'] ?? 5);
        $backoffSeconds = (int) ($options['backoff_seconds'] ?? 10);

        $job = new WebhookJob(
            url: $url,
            payload: $payload,
            secret: $secret,
            headers: $headers,
            maxAttempts: $maxAttempts,
            backoffSeconds: $backoffSeconds,
        );
        $job->queue = $queue;
        $job->tries = $maxAttempts;

        if (class_exists(Queue::class)) {
            try {
                Queue::push($job, $queue);
            } catch (\Throwable) {
                // Execute synchronously if queue not configured
                $job->handle();
            }
        } else {
            $job->handle();
        }

        return $job;
    }

    /**
     * Clear recorded delivery history.
     */
    public static function clearHistory(): void
    {
        self::$deliveryHistory = [];
    }
}

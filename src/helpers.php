<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Switch\Foundation\Api\ApiResponse;
use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Guard\GuardInterface;
use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Image\Image;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Queue\Facade\Queue;
use Switch\Foundation\Queue\Job;
use Switch\Foundation\Storage\FilesystemInterface;
use Switch\Foundation\Storage\StorageManager;

if (!function_exists('auth')) {
    /**
     * Get the available auth instance or active authenticated user.
     *
     * @param string|null $guard
     * @return AuthManager|GuardInterface|AuthenticatableInterface|null
     */
    function auth(?string $guard = null): mixed
    {
        $manager = AuthManager::getInstance();

        if ($guard === null) {
            return $manager;
        }

        return $manager->guard($guard);
    }
}

if (!function_exists('cache')) {
    /**
     * Get / set cache values or retrieve the CacheManager instance.
     */
    function cache(string|array|null $key = null, mixed $default = null): mixed
    {
        $manager = CacheManager::getInstance();

        if ($key === null) {
            return $manager;
        }

        if (is_array($key)) {
            // ['key' => 'value'], or ['key' => ['value', $ttl]]
            foreach ($key as $k => $val) {
                if (is_array($val) && count($val) === 2) {
                    $manager->put($k, $val[0], (int) $val[1]);
                } else {
                    $manager->put($k, $val);
                }
            }
            return true;
        }

        return $manager->get($key, $default);
    }
}

if (!function_exists('storage')) {
    /**
     * Get the storage disk instance or StorageManager.
     */
    function storage(?string $disk = null): StorageManager|FilesystemInterface
    {
        $manager = StorageManager::getInstance();

        if ($disk === null) {
            return $manager;
        }

        return $manager->disk($disk);
    }
}

if (!function_exists('image')) {
    /**
     * Load an image for manipulation.
     */
    function image(string $path): Image
    {
        return Image::load($path);
    }
}

if (!function_exists('mail_manager')) {
    /**
     * Get the MailManager instance.
     */
    function mail_manager(): MailManager
    {
        return MailManager::getInstance();
    }
}

if (!function_exists('dispatch')) {
    /**
     * Dispatch a background job to the queue.
     */
    function dispatch(Job $job, string $queue = 'default'): string|int
    {
        return Queue::push($job, $queue);
    }
}

if (!function_exists('response_json')) {
    /**
     * Return a standardized JSON API response.
     */
    function response_json(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): ResponseInterface
    {
        return ApiResponse::success($data, $message, $status, $meta);
    }
}

if (!function_exists('notify')) {
    /**
     * Send a notification to notifiables.
     */
    function notify(mixed $notifiables, \Switch\Foundation\Notification\Notification $notification, ?array $channels = null): void
    {
        \Switch\Foundation\Notification\Facade\Notification::send($notifiables, $notification, $channels);
    }
}

if (!function_exists('notification')) {
    /**
     * Get the NotificationManager instance.
     */
    function notification(): \Switch\Foundation\Notification\NotificationManager
    {
        return \Switch\Foundation\Notification\NotificationManager::getInstance();
    }
}

if (!function_exists('notification_stream')) {
    /**
     * Render the zero-config client-side SSE script.
     */
    function notification_stream(string $streamUrl = '/api/notifications/stream'): string
    {
        return \Switch\Foundation\Notification\Realtime\NotificationStream::renderScript($streamUrl);
    }
}

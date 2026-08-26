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

if (!function_exists('passwordless')) {
    /**
     * Get the PasswordlessManager instance.
     */
    function passwordless(): \Switch\Foundation\Auth\Passwordless\PasswordlessManager
    {
        return \Switch\Foundation\Auth\Passwordless\PasswordlessManager::getInstance();
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

if (!function_exists('context')) {
    /**
     * Get or set a named context value.
     *
     * context()                          => ContextManager instance
     * context('theme')                   => current value of 'theme' context
     * context('theme.mode')              => dot-notation access
     * context('theme', 'dark')           => get with default
     * context(['theme' => [...]])        => provide multiple contexts
     */
    function context(string|array|null $name = null, mixed $default = null): mixed
    {
        $facade = \Switch\Foundation\Context\Facade\Context::class;

        if ($name === null) {
            return $facade::getManager();
        }

        if (is_array($name)) {
            $facade::getManager()->provideMany($name);
            return $facade::getManager();
        }

        return $facade::use($name, $default);
    }
}

if (!function_exists('data')) {
    /**
     * Load or retrieve static data by source key with dot-notation.
     *
     * data()                   => DataManager instance
     * data('countries')        => load entire dataset
     * data('countries.US')     => dot-notation access
     */
    function data(string|null $key = null, mixed $default = null): mixed
    {
        $facade = \Switch\Foundation\Data\Facade\Data::class;

        if ($key === null) {
            return $facade::getManager();
        }

        return $facade::get($key, $default);
    }
}

if (!function_exists('mock')) {
    /**
     * Generate mock data records using registered blueprints.
     *
     * mock('user')              => 1 mock user record
     * mock('user', 5)           => 5 mock user records
     * mock('product', 3, [...]) => 3 products with overrides
     */
    function mock(string $blueprint, int $count = 1, array $overrides = []): array
    {
        return \Switch\Foundation\Data\Facade\Data::mock($blueprint, $count, $overrides);
    }
}

if (!function_exists('fake')) {
    /**
     * Generate a single fake value by type.
     *
     * fake('name')     => random name
     * fake('email')    => random email
     * fake('uuid')     => random UUID
     * fake()           => MockGenerator instance
     */
    function fake(?string $type = null, ...$args): mixed
    {
        return \Switch\Foundation\Data\Facade\Data::fake($type, ...$args);
    }
}

if (!function_exists('collect')) {
    /**
     * Create a new Collection instance from items.
     *
     * @template TKey of array-key
     * @template TValue
     * @param mixed $items
     * @return \Switch\Foundation\Collection\Collection<TKey, TValue>
     */
    function collect(mixed $items = []): \Switch\Foundation\Collection\Collection
    {
        return new \Switch\Foundation\Collection\Collection($items);
    }
}


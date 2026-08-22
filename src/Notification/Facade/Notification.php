<?php

declare(strict_types=1);

namespace Switch\Foundation\Notification\Facade;

use Switch\Foundation\Notification\AnonymousNotifiable;
use Switch\Foundation\Notification\Channel\ChannelInterface;
use Switch\Foundation\Notification\Notification as NotificationInstance;
use Switch\Foundation\Notification\NotificationManager;

/**
 * Static Notification Facade.
 *
 * @method static void send(iterable|mixed $notifiables, NotificationInstance $notification, ?array $channels = null)
 * @method static void sendNow(iterable|mixed $notifiables, NotificationInstance $notification, ?array $channels = null)
 * @method static AnonymousNotifiable route(string $channel, mixed $route)
 * @method static ChannelInterface channel(string $name)
 */
class Notification
{
    public static function send(mixed $notifiables, NotificationInstance $notification, ?array $channels = null): void
    {
        NotificationManager::getInstance()->send($notifiables, $notification, $channels);
    }

    public static function sendNow(mixed $notifiables, NotificationInstance $notification, ?array $channels = null): void
    {
        NotificationManager::getInstance()->sendNow($notifiables, $notification, $channels);
    }

    public static function route(string $channel, mixed $route): AnonymousNotifiable
    {
        return NotificationManager::getInstance()->route($channel, $route);
    }

    public static function channel(string $name): ChannelInterface
    {
        return NotificationManager::getInstance()->channel($name);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return NotificationManager::getInstance()->$method(...$arguments);
    }
}

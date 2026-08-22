<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue\Facade;

use Switch\Foundation\Queue\Driver\QueueDriverInterface;
use Switch\Foundation\Queue\Job;
use Switch\Foundation\Queue\QueueManager;

/**
 * Static Queue Facade.
 *
 * @method static QueueDriverInterface connection(?string $name = null)
 * @method static string|int push(Job $job, string $queue = 'default')
 * @method static string|int later(int $delay, Job $job, string $queue = 'default')
 * @method static Job|null pop(string $queue = 'default')
 * @method static int size(string $queue = 'default')
 * @method static int clear(string $queue = 'default')
 */
class Queue
{
    public static function connection(?string $name = null): QueueDriverInterface
    {
        return QueueManager::getInstance()->connection($name);
    }

    public static function push(Job $job, string $queue = 'default'): string|int
    {
        return QueueManager::getInstance()->push($job, $queue);
    }

    public static function later(int $delay, Job $job, string $queue = 'default'): string|int
    {
        return QueueManager::getInstance()->later($delay, $job, $queue);
    }

    public static function pop(string $queue = 'default'): ?Job
    {
        return QueueManager::getInstance()->pop($queue);
    }

    public static function size(string $queue = 'default'): int
    {
        return QueueManager::getInstance()->size($queue);
    }

    public static function clear(string $queue = 'default'): int
    {
        return QueueManager::getInstance()->clear($queue);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return QueueManager::getInstance()->$method(...$arguments);
    }
}

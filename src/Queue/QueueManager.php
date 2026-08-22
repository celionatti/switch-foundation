<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue;

use InvalidArgumentException;
use Switch\Foundation\Queue\Driver\ArrayDriver;
use Switch\Foundation\Queue\Driver\DatabaseDriver;
use Switch\Foundation\Queue\Driver\QueueDriverInterface;
use Switch\Foundation\Queue\Driver\SyncDriver;

class QueueManager
{
    private static ?self $instance = null;
    private array $config;
    private array $connections = [];
    private array $customDrivers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'sync',
            'connections' => [
                'sync' => [
                    'driver' => 'sync',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'jobs',
                    'retry_after' => 90,
                ],
                'array' => [
                    'driver' => 'array',
                ],
            ],
        ], $config);
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function connection(?string $name = null): QueueDriverInterface
    {
        $name ??= $this->getDefaultDriver();

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->resolve($name);
    }

    public function extend(string $driver, callable $callback): static
    {
        $this->customDrivers[$driver] = $callback;
        return $this;
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'sync';
    }

    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    public function push(Job $job, string $queue = 'default'): string|int
    {
        return $this->connection()->push($job, $queue);
    }

    public function later(int $delay, Job $job, string $queue = 'default'): string|int
    {
        return $this->connection()->later($delay, $job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return $this->connection()->pop($queue);
    }

    public function size(string $queue = 'default'): int
    {
        return $this->connection()->size($queue);
    }

    public function clear(string $queue = 'default'): int
    {
        return $this->connection()->clear($queue);
    }

    public function getWorker(): Worker
    {
        return new Worker($this);
    }

    private function resolve(string $name): QueueDriverInterface
    {
        $config = $this->config['connections'][$name] ?? ['driver' => $name];
        $driver = $config['driver'] ?? $name;

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this, $config);
        }

        return match ($driver) {
            'sync' => new SyncDriver(),
            'array' => new ArrayDriver(),
            'database' => new DatabaseDriver(null, $config['table'] ?? 'jobs', (int) ($config['retry_after'] ?? 90)),
            default => throw new InvalidArgumentException("Queue driver [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}

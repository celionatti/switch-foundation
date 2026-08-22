<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue;

use Switch\Foundation\Queue\Facade\Queue;
use Throwable;

abstract class Job
{
    public int $tries = 3;
    public int $timeout = 60;
    public int $attempts = 0;
    public int $delay = 0;
    public string $queue = 'default';
    public ?string $jobId = null;

    abstract public function handle(): void;

    public function failed(Throwable $e): void
    {
        // Optional override hook for failure handling / notifications
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    public function tries(int $tries): static
    {
        $this->tries = $tries;
        return $this;
    }

    public static function dispatch(mixed ...$arguments): static
    {
        $job = new static(...$arguments);
        Queue::push($job);
        return $job;
    }
}

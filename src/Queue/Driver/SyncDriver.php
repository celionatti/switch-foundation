<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue\Driver;

use Switch\Foundation\Queue\Job;
use Throwable;

class SyncDriver implements QueueDriverInterface
{
    public function push(Job $job, string $queue = 'default'): string|int
    {
        $job->attempts++;
        try {
            $job->handle();
        } catch (Throwable $e) {
            $job->failed($e);
            throw $e;
        }

        return 1;
    }

    public function later(int $delay, Job $job, string $queue = 'default'): string|int
    {
        return $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function clear(string $queue = 'default'): int
    {
        return 0;
    }
}

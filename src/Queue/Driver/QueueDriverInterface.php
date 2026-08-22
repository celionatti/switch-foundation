<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue\Driver;

use Switch\Foundation\Queue\Job;

interface QueueDriverInterface
{
    public function push(Job $job, string $queue = 'default'): string|int;

    public function later(int $delay, Job $job, string $queue = 'default'): string|int;

    public function pop(string $queue = 'default'): ?Job;

    public function size(string $queue = 'default'): int;

    public function clear(string $queue = 'default'): int;
}

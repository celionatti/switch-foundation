<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue\Driver;

use Switch\Foundation\Queue\Job;

class ArrayDriver implements QueueDriverInterface
{
    private array $queues = [];
    private int $increment = 1;

    public function push(Job $job, string $queue = 'default'): string|int
    {
        $id = $this->increment++;
        $job->jobId = (string) $id;
        $this->queues[$queue][] = [
            'id' => $id,
            'job' => $job,
            'available_at' => time() + $job->delay,
        ];

        return $id;
    }

    public function later(int $delay, Job $job, string $queue = 'default'): string|int
    {
        $job->delay = $delay;
        return $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        if (empty($this->queues[$queue])) {
            return null;
        }

        $now = time();
        foreach ($this->queues[$queue] as $idx => $entry) {
            if ($entry['available_at'] <= $now) {
                unset($this->queues[$queue][$idx]);
                $this->queues[$queue] = array_values($this->queues[$queue]);
                return $entry['job'];
            }
        }

        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return count($this->queues[$queue] ?? []);
    }

    public function clear(string $queue = 'default'): int
    {
        $count = $this->size($queue);
        $this->queues[$queue] = [];
        return $count;
    }

    public function all(string $queue = 'default'): array
    {
        return array_map(fn($item) => $item['job'], $this->queues[$queue] ?? []);
    }
}

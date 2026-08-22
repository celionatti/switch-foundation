<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue;

use Switch\Foundation\Queue\Driver\DatabaseDriver;
use Switch\Foundation\Queue\Driver\QueueDriverInterface;
use Throwable;

class Worker
{
    private QueueManager $manager;

    public function __construct(QueueManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Process the next job in the specified queue.
     *
     * @return bool True if a job was processed, false if queue is empty.
     */
    public function processNextJob(string $queue = 'default', ?string $connection = null): bool
    {
        $driver = $this->manager->connection($connection);
        $job = $driver->pop($queue);

        if ($job === null) {
            return false;
        }

        try {
            $job->handle();

            if ($driver instanceof DatabaseDriver && $job->jobId !== null) {
                $driver->deleteJob((int) $job->jobId);
            }

            return true;
        } catch (Throwable $e) {
            if ($job->attempts >= $job->tries) {
                $job->failed($e);
                if ($driver instanceof DatabaseDriver && $job->jobId !== null) {
                    $driver->deleteJob((int) $job->jobId);
                }
            }
            throw $e;
        }
    }

    /**
     * Run worker daemon loop.
     */
    public function daemon(string $queue = 'default', int $sleep = 3, int $maxJobs = 0): void
    {
        $jobsProcessed = 0;

        while (true) {
            $ran = false;
            try {
                $ran = $this->processNextJob($queue);
            } catch (Throwable $e) {
                // Log and continue loop in daemon mode
            }

            if ($ran) {
                $jobsProcessed++;
                if ($maxJobs > 0 && $jobsProcessed >= $maxJobs) {
                    break;
                }
            } else {
                sleep($sleep);
            }
        }
    }
}

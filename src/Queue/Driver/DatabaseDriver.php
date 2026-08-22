<?php

declare(strict_types=1);

namespace Switch\Foundation\Queue\Driver;

use PDO;
use Switch\Database\DB;
use Switch\Foundation\Queue\Job;

class DatabaseDriver implements QueueDriverInterface
{
    private ?PDO $pdo;
    private string $table;
    private int $retryAfter;

    public function __construct(?PDO $pdo = null, string $table = 'jobs', int $retryAfter = 90)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->retryAfter = $retryAfter;
    }

    public function push(Job $job, string $queue = 'default'): string|int
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return 0;
        }

        $now = time();
        $availableAt = $now + $job->delay;
        $payload = serialize($job);

        $stmt = $pdo->prepare(
            "INSERT INTO `{$this->table}` (`queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`)
             VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)"
        );

        $stmt->execute([
            ':queue' => $queue,
            ':payload' => $payload,
            ':available_at' => $availableAt,
            ':created_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function later(int $delay, Job $job, string $queue = 'default'): string|int
    {
        $job->delay = $delay;
        return $this->push($job, $queue);
    }

    public function pop(string $queue = 'default'): ?Job
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return null;
        }

        $now = time();
        $expired = $now - $this->retryAfter;

        // Reserve next available job atomically
        $stmt = $pdo->prepare(
            "SELECT `id`, `payload`, `attempts` FROM `{$this->table}`
             WHERE `queue` = :queue AND (`reserved_at` IS NULL OR `reserved_at` <= :expired) AND `available_at` <= :now
             ORDER BY `id` ASC LIMIT 1"
        );

        $stmt->execute([
            ':queue' => $queue,
            ':expired' => $expired,
            ':now' => $now,
        ]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$record) {
            return null;
        }

        $id = (int) $record['id'];
        $attempts = (int) $record['attempts'] + 1;

        // Mark as reserved
        $update = $pdo->prepare("UPDATE `{$this->table}` SET `reserved_at` = :now, `attempts` = :attempts WHERE `id` = :id");
        $update->execute([':now' => $now, ':attempts' => $attempts, ':id' => $id]);

        try {
            $job = unserialize($record['payload']);
            if ($job instanceof Job) {
                $job->jobId = (string) $id;
                $job->attempts = $attempts;
                return $job;
            }
        } catch (\Throwable) {
            $this->deleteJob($id);
        }

        return null;
    }

    public function deleteJob(int $id): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM `{$this->table}` WHERE `id` = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function size(string $queue = 'default'): int
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$this->table}` WHERE `queue` = :queue");
        $stmt->execute([':queue' => $queue]);
        return (int) $stmt->fetchColumn();
    }

    public function clear(string $queue = 'default'): int
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return 0;
        }

        $count = $this->size($queue);
        $stmt = $pdo->prepare("DELETE FROM `{$this->table}` WHERE `queue` = :queue");
        $stmt->execute([':queue' => $queue]);
        return $count;
    }

    private function getPdo(): ?PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        if (class_exists(DB::class)) {
            try {
                return DB::getPdo();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}

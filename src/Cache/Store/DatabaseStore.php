<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache\Store;

use PDO;
use Switch\Database\DB;

class DatabaseStore implements CacheStoreInterface
{
    private ?PDO $pdo;
    private string $table;

    public function __construct(?PDO $pdo = null, string $table = 'cache')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return $default;
        }

        $stmt = $pdo->prepare("SELECT `value`, `expiration` FROM `{$this->table}` WHERE `key` = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return $default;
        }

        $expire = (int) $row['expiration'];
        if ($expire !== 0 && time() >= $expire) {
            $this->forget($key);
            return $default;
        }

        try {
            return unserialize($row['value']);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        $expire = $seconds > 0 ? (time() + $seconds) : 0;
        $serialized = serialize($value);

        $stmt = $pdo->prepare(
            "INSERT INTO `{$this->table}` (`key`, `value`, `expiration`) VALUES (:key, :value, :expiration)
             ON DUPLICATE KEY UPDATE `value` = :value_update, `expiration` = :exp_update"
        );

        // Fallback for SQLite / PostgreSQL syntax
        try {
            return $stmt->execute([
                ':key' => $key,
                ':value' => $serialized,
                ':expiration' => $expire,
                ':value_update' => $serialized,
                ':exp_update' => $expire,
            ]);
        } catch (\Throwable) {
            // Portable DELETE + INSERT fallback
            $this->forget($key);
            $insert = $pdo->prepare("INSERT INTO `{$this->table}` (`key`, `value`, `expiration`) VALUES (:key, :value, :expiration)");
            return $insert->execute([
                ':key' => $key,
                ':value' => $serialized,
                ':expiration' => $expire,
            ]);
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        $stmt = $pdo->prepare("DELETE FROM `{$this->table}` WHERE `key` = :key");
        return $stmt->execute([':key' => $key]);
    }

    public function flush(): bool
    {
        $pdo = $this->getPdo();
        if ($pdo === null) {
            return false;
        }

        return (bool) $pdo->exec("DELETE FROM `{$this->table}`");
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->put($key, $new);
        return $new;
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->increment($key, $value * -1);
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $callback();
        $this->put($key, $value, $seconds);
        return $value;
    }

    public function many(array $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    public function putMany(array $values, int $seconds = 0): bool
    {
        foreach ($values as $key => $value) {
            $this->put($key, $value, $seconds);
        }
        return true;
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

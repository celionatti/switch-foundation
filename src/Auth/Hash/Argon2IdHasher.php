<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Hash;

use RuntimeException;

class Argon2IdHasher implements HasherInterface
{
    private int $memory;
    private int $time;
    private int $threads;

    public function __construct(int $memory = 65536, int $time = 4, int $threads = 1)
    {
        $this->memory = $memory;
        $this->time = $time;
        $this->threads = $threads;
    }

    public function make(string $value, array $options = []): string
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            // Fallback to bcrypt if Argon2id is not enabled in PHP compile
            $fallback = new BcryptHasher();
            return $fallback->make($value, $options);
        }

        $options = [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ];

        $hash = password_hash($value, PASSWORD_ARGON2ID, $options);

        if ($hash === false) {
            throw new RuntimeException('Argon2Id hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        if (empty($hashedValue)) {
            return false;
        }

        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            return true;
        }

        $options = [
            'memory_cost' => $options['memory'] ?? $this->memory,
            'time_cost' => $options['time'] ?? $this->time,
            'threads' => $options['threads'] ?? $this->threads,
        ];

        return password_needs_rehash($hashedValue, PASSWORD_ARGON2ID, $options);
    }
}

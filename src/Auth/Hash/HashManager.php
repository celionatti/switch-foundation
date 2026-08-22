<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Hash;

use InvalidArgumentException;

class HashManager
{
    private static ?self $instance = null;
    private array $config;
    private array $hashers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'driver' => 'bcrypt',
            'bcrypt' => [
                'rounds' => 12,
            ],
            'argon' => [
                'memory' => 65536,
                'time' => 4,
                'threads' => 1,
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

    public function driver(?string $driver = null): HasherInterface
    {
        $driver ??= $this->config['driver'] ?? 'bcrypt';

        if (isset($this->hashers[$driver])) {
            return $this->hashers[$driver];
        }

        return $this->hashers[$driver] = $this->createDriver($driver);
    }

    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    private function createDriver(string $driver): HasherInterface
    {
        return match ($driver) {
            'bcrypt' => new BcryptHasher((int) ($this->config['bcrypt']['rounds'] ?? 12)),
            'argon', 'argon2id' => new Argon2IdHasher(
                (int) ($this->config['argon']['memory'] ?? 65536),
                (int) ($this->config['argon']['time'] ?? 4),
                (int) ($this->config['argon']['threads'] ?? 1)
            ),
            default => throw new InvalidArgumentException("Unsupported hash driver [{$driver}]."),
        };
    }
}

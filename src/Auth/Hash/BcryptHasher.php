<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Hash;

use RuntimeException;

class BcryptHasher implements HasherInterface
{
    private int $rounds;

    public function __construct(int $rounds = 12)
    {
        $this->rounds = $rounds;
    }

    public function make(string $value, array $options = []): string
    {
        $cost = $options['rounds'] ?? $options['cost'] ?? $this->rounds;
        $hash = password_hash($value, PASSWORD_BCRYPT, ['cost' => $cost]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported or failed.');
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
        $cost = $options['rounds'] ?? $options['cost'] ?? $this->rounds;
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, ['cost' => $cost]);
    }
}

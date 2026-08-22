<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Facade;

use Switch\Foundation\Auth\Hash\HashManager;

class Hash
{
    public static function make(string $value, array $options = []): string
    {
        return HashManager::getInstance()->make($value, $options);
    }

    public static function check(string $value, string $hashedValue, array $options = []): bool
    {
        return HashManager::getInstance()->check($value, $hashedValue, $options);
    }

    public static function needsRehash(string $hashedValue, array $options = []): bool
    {
        return HashManager::getInstance()->needsRehash($hashedValue, $options);
    }
}

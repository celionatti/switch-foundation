<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Hash;

interface HasherInterface
{
    public function make(string $value, array $options = []): string;

    public function check(string $value, string $hashedValue, array $options = []): bool;

    public function needsRehash(string $hashedValue, array $options = []): bool;
}

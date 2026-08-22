<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache\Store;

interface CacheStoreInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, int $seconds = 0): bool;

    public function has(string $key): bool;

    public function forget(string $key): bool;

    public function flush(): bool;

    public function increment(string $key, int $value = 1): int|bool;

    public function decrement(string $key, int $value = 1): int|bool;

    /**
     * Get an item from cache, or store the default value if not found.
     */
    public function remember(string $key, int $seconds, callable $callback): mixed;

    public function many(array $keys): array;

    public function putMany(array $values, int $seconds = 0): bool;
}

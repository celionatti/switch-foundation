<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache\Store;

class ArrayStore implements CacheStoreInterface
{
    private array $storage = [];
    private array $expiration = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->storage[$key];
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        $this->storage[$key] = $value;

        if ($seconds > 0) {
            $this->expiration[$key] = time() + $seconds;
        } else {
            unset($this->expiration[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (!array_key_exists($key, $this->storage)) {
            return false;
        }

        if (isset($this->expiration[$key]) && time() >= $this->expiration[$key]) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    public function forget(string $key): bool
    {
        unset($this->storage[$key], $this->expiration[$key]);
        return true;
    }

    public function flush(): bool
    {
        $this->storage = [];
        $this->expiration = [];
        return true;
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        $current = (int) $this->get($key, 0);
        $new = $current + $value;
        $this->storage[$key] = $new;
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
}

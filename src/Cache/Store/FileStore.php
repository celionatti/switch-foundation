<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache\Store;

class FileStore implements CacheStoreInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/\\');
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return $default;
        }

        $expire = (int) substr($contents, 0, 10);

        if ($expire !== 0 && time() >= $expire) {
            $this->forget($key);
            return $default;
        }

        try {
            $data = unserialize(substr($contents, 10));
            return $data;
        } catch (\Throwable) {
            $this->forget($key);
            return $default;
        }
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        $path = $this->path($key);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $expire = $seconds > 0 ? (time() + $seconds) : 0;
        $payload = sprintf('%010d', $expire) . serialize($value);

        // Atomic write
        $tempPath = $path . '.' . uniqid('temp_', true);
        if (@file_put_contents($tempPath, $payload, LOCK_EX) === false) {
            return false;
        }

        return @rename($tempPath, $path);
    }

    public function has(string $key): bool
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return false;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }

        $expire = (int) substr($contents, 0, 10);
        if ($expire !== 0 && time() >= $expire) {
            $this->forget($key);
            return false;
        }

        return true;
    }

    public function forget(string $key): bool
    {
        $path = $this->path($key);
        if (file_exists($path)) {
            return @unlink($path);
        }
        return true;
    }

    public function flush(): bool
    {
        return $this->deleteDirectory($this->directory);
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

    protected function path(string $key): string
    {
        $hash = sha1($key);
        $parts = array_slice(str_split($hash, 2), 0, 2);
        return $this->directory . '/' . implode('/', $parts) . '/' . $hash;
    }

    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return true;
        }

        $files = scandir($dir);
        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache;

use InvalidArgumentException;
use Switch\Foundation\Cache\Store\ArrayStore;
use Switch\Foundation\Cache\Store\CacheStoreInterface;
use Switch\Foundation\Cache\Store\DatabaseStore;
use Switch\Foundation\Cache\Store\FileStore;

class CacheManager
{
    private static ?self $instance = null;
    private array $config;
    private array $stores = [];
    private array $customDrivers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'file',
            'stores' => [
                'file' => [
                    'driver' => 'file',
                    'path' => 'storage/framework/cache/data',
                ],
                'array' => [
                    'driver' => 'array',
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => 'cache',
                ],
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

    public function store(?string $name = null): CacheStoreInterface
    {
        $name ??= $this->getDefaultDriver();

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        return $this->stores[$name] = $this->resolve($name);
    }

    public function driver(?string $name = null): CacheStoreInterface
    {
        return $this->store($name);
    }

    public function extend(string $driver, callable $callback): static
    {
        $this->customDrivers[$driver] = $callback;
        return $this;
    }

    public function tags(array|string $names): TaggedCache
    {
        $tags = is_array($names) ? $names : func_get_args();
        return new TaggedCache($this->store(), $tags);
    }

    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'file';
    }

    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $seconds = 0): bool
    {
        return $this->store()->put($key, $value, $seconds);
    }

    public function has(string $key): bool
    {
        return $this->store()->has($key);
    }

    public function forget(string $key): bool
    {
        return $this->store()->forget($key);
    }

    public function flush(): bool
    {
        return $this->store()->flush();
    }

    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        return $this->store()->remember($key, $seconds, $callback);
    }

    public function increment(string $key, int $value = 1): int|bool
    {
        return $this->store()->increment($key, $value);
    }

    public function decrement(string $key, int $value = 1): int|bool
    {
        return $this->store()->decrement($key, $value);
    }

    private function resolve(string $name): CacheStoreInterface
    {
        $config = $this->config['stores'][$name] ?? ['driver' => $name];
        $driver = $config['driver'] ?? $name;

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this, $config);
        }

        return match ($driver) {
            'array' => new ArrayStore(),
            'file' => new FileStore($config['path'] ?? 'storage/framework/cache/data'),
            'database' => new DatabaseStore(null, $config['table'] ?? 'cache'),
            default => throw new InvalidArgumentException("Cache driver [{$driver}] is not supported."),
        };
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->store()->$method(...$parameters);
    }
}

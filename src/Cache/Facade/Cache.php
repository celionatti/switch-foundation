<?php

declare(strict_types=1);

namespace Switch\Foundation\Cache\Facade;

use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Cache\Store\CacheStoreInterface;
use Switch\Foundation\Cache\TaggedCache;

/**
 * Static Cache Facade.
 *
 * @method static CacheStoreInterface store(?string $name = null)
 * @method static CacheStoreInterface driver(?string $name = null)
 * @method static TaggedCache tags(array|string $names)
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool put(string $key, mixed $value, int $seconds = 0)
 * @method static bool has(string $key)
 * @method static bool forget(string $key)
 * @method static bool flush()
 * @method static mixed remember(string $key, int $seconds, callable $callback)
 * @method static int|bool increment(string $key, int $value = 1)
 * @method static int|bool decrement(string $key, int $value = 1)
 */
class Cache
{
    public static function store(?string $name = null): CacheStoreInterface
    {
        return CacheManager::getInstance()->store($name);
    }

    public static function driver(?string $name = null): CacheStoreInterface
    {
        return CacheManager::getInstance()->driver($name);
    }

    public static function tags(array|string $names): TaggedCache
    {
        return CacheManager::getInstance()->tags($names);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return CacheManager::getInstance()->get($key, $default);
    }

    public static function put(string $key, mixed $value, int $seconds = 0): bool
    {
        return CacheManager::getInstance()->put($key, $value, $seconds);
    }

    public static function has(string $key): bool
    {
        return CacheManager::getInstance()->has($key);
    }

    public static function forget(string $key): bool
    {
        return CacheManager::getInstance()->forget($key);
    }

    public static function flush(): bool
    {
        return CacheManager::getInstance()->flush();
    }

    public static function remember(string $key, int $seconds, callable $callback): mixed
    {
        return CacheManager::getInstance()->remember($key, $seconds, $callback);
    }

    public static function increment(string $key, int $value = 1): int|bool
    {
        return CacheManager::getInstance()->increment($key, $value);
    }

    public static function decrement(string $key, int $value = 1): int|bool
    {
        return CacheManager::getInstance()->decrement($key, $value);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return CacheManager::getInstance()->$method(...$arguments);
    }
}

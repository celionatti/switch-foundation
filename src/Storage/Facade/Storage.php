<?php

declare(strict_types=1);

namespace Switch\Foundation\Storage\Facade;

use Switch\Foundation\Storage\FilesystemInterface;
use Switch\Foundation\Storage\StorageManager;

/**
 * Static Storage Facade.
 *
 * @method static FilesystemInterface disk(?string $name = null)
 * @method static string|null get(string $path)
 * @method static bool put(string $path, string $contents)
 * @method static bool exists(string $path)
 * @method static bool missing(string $path)
 * @method static bool delete(array|string $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string url(string $path)
 * @method static string path(string $path)
 * @method static string|null mimeType(string $path)
 * @method static string|false putFile(string $path, string $file)
 */
class Storage
{
    public static function disk(?string $name = null): FilesystemInterface
    {
        return StorageManager::getInstance()->disk($name);
    }

    public static function get(string $path): ?string
    {
        return StorageManager::getInstance()->get($path);
    }

    public static function put(string $path, string $contents): bool
    {
        return StorageManager::getInstance()->put($path, $contents);
    }

    public static function exists(string $path): bool
    {
        return StorageManager::getInstance()->exists($path);
    }

    public static function delete(array|string $paths): bool
    {
        return StorageManager::getInstance()->delete($paths);
    }

    public static function url(string $path): string
    {
        return StorageManager::getInstance()->url($path);
    }

    public static function path(string $path): string
    {
        return StorageManager::getInstance()->path($path);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return StorageManager::getInstance()->$method(...$arguments);
    }
}

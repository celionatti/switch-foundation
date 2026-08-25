<?php

declare(strict_types=1);

namespace Switch\Foundation\Data\Facade;

use Switch\Foundation\Data\DataManager;
use Switch\Foundation\Data\MockGenerator;

/**
 * Static Data & Mock Facade.
 */
class Data
{
    private static ?DataManager $manager = null;

    public static function getManager(): DataManager
    {
        if (self::$manager === null) {
            self::$manager = new DataManager();
        }

        return self::$manager;
    }

    public static function setManager(DataManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function load(string $source): mixed
    {
        return self::getManager()->load($source);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getManager()->get($key, $default);
    }

    public static function set(string $key, mixed $value): DataManager
    {
        return self::getManager()->set($key, $value);
    }

    public static function has(string $key): bool
    {
        return self::getManager()->has($key);
    }

    public static function where(string $source, string $field, mixed $value): array
    {
        return self::getManager()->where($source, $field, $value);
    }

    public static function find(string $source, mixed $id, string $idField = 'id'): ?array
    {
        return self::getManager()->find($source, $id, $idField);
    }

    public static function pluck(string $source, string $column, ?string $indexKey = null): array
    {
        return self::getManager()->pluck($source, $column, $indexKey);
    }

    public static function fake(?string $type = null, ...$args): mixed
    {
        return self::getManager()->fake($type, ...$args);
    }

    public static function faker(): MockGenerator
    {
        return self::getManager()->faker();
    }

    public static function define(string $blueprint, callable $factory): DataManager
    {
        return self::getManager()->define($blueprint, $factory);
    }

    public static function mock(string $blueprint, int $count = 1, array $overrides = []): array
    {
        return self::getManager()->mock($blueprint, $count, $overrides);
    }

    public static function addPath(string $path): DataManager
    {
        return self::getManager()->addPath($path);
    }

    public static function all(): array
    {
        return self::getManager()->all();
    }

    public static function clear(): void
    {
        self::getManager()->clear();
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::getManager()->$method(...$arguments);
    }
}

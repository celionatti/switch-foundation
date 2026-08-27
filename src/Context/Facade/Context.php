<?php

declare(strict_types=1);

namespace Switch\Foundation\Context\Facade;

use Switch\Foundation\Context\Context as ContextInstance;
use Switch\Foundation\Context\ContextManager;
use Closure;

/**
 * Static Context Facade.
 */
class Context
{
    private static ?ContextManager $manager = null;

    public static function getManager(): ContextManager
    {
        if (self::$manager === null) {
            self::$manager = new ContextManager();
        }

        return self::$manager;
    }

    public static function setManager(ContextManager $manager): void
    {
        self::$manager = $manager;
    }

    public static function context(string $name, mixed $default = null): ContextInstance
    {
        return self::getManager()->context($name, $default);
    }

    public static function provide(string $name, mixed $value, ?callable $callback = null): mixed
    {
        return self::getManager()->provide($name, $value, $callback);
    }

    public static function share(string $name, mixed $value, ?callable $callback = null): mixed
    {
        return self::getManager()->share($name, $value, $callback);
    }

    public static function provideMany(array $contexts, ?callable $callback = null): mixed
    {
        return self::getManager()->provideMany($contexts, $callback);
    }

    public static function use(string $name, mixed $default = null): mixed
    {
        return self::getManager()->use($name, $default);
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        return self::getManager()->use($name, $default);
    }

    public static function set(string $name, mixed $value): ContextInstance
    {
        return self::getManager()->context($name)->set($value);
    }

    public static function mutate(string $name, callable $mutator): mixed
    {
        return self::getManager()->mutate($name, $mutator);
    }

    public static function merge(string $name, array $data): ContextInstance
    {
        return self::getManager()->merge($name, $data);
    }

    public static function subscribe(string $name, callable $listener): Closure
    {
        return self::getManager()->subscribe($name, $listener);
    }

    public static function has(string $name): bool
    {
        return self::getManager()->has($name);
    }

    public static function markClient(string $name, bool $sync = true): ContextManager
    {
        return self::getManager()->markClient($name, $sync);
    }

    public static function getClientPayload(): array
    {
        return self::getManager()->getClientPayload();
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

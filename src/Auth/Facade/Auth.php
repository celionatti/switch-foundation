<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Facade;

use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Guard\GuardInterface;

/**
 * Static Auth Facade.
 *
 * @method static GuardInterface guard(?string $name = null)
 * @method static AuthenticatableInterface|null user()
 * @method static bool check()
 * @method static bool guest()
 * @method static mixed id()
 * @method static bool validate(array $credentials = [])
 * @method static bool attempt(array $credentials = [], bool $remember = false)
 * @method static void login(AuthenticatableInterface $user, bool $remember = false)
 * @method static void logout()
 * @method static void setUser(AuthenticatableInterface $user)
 */
class Auth
{
    public static function guard(?string $name = null): GuardInterface
    {
        return AuthManager::getInstance()->guard($name);
    }

    public static function user(): ?AuthenticatableInterface
    {
        return AuthManager::getInstance()->user();
    }

    public static function check(): bool
    {
        return AuthManager::getInstance()->check();
    }

    public static function guest(): bool
    {
        return AuthManager::getInstance()->guest();
    }

    public static function id(): mixed
    {
        return AuthManager::getInstance()->id();
    }

    public static function attempt(array $credentials = [], bool $remember = false): bool
    {
        return AuthManager::getInstance()->attempt($credentials, $remember);
    }

    public static function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        AuthManager::getInstance()->login($user, $remember);
    }

    public static function logout(): void
    {
        AuthManager::getInstance()->logout();
    }

    public static function setUser(AuthenticatableInterface $user): void
    {
        AuthManager::getInstance()->setUser($user);
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return AuthManager::getInstance()->$method(...$arguments);
    }
}

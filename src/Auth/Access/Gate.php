<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Access;

use RuntimeException;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\AuthenticatableInterface;

class Gate
{
    private static ?self $instance = null;
    private array $abilities = [];
    private array $policies = [];
    private ?AuthenticatableInterface $user = null;

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public static function define(string $ability, callable $callback): void
    {
        self::getInstance()->abilities[$ability] = $callback;
    }

    public static function policy(string $class, string $policy): void
    {
        self::getInstance()->policies[$class] = $policy;
    }

    public static function forUser(?AuthenticatableInterface $user): self
    {
        $clone = clone self::getInstance();
        $clone->user = $user;
        return $clone;
    }

    public static function allows(string $ability, mixed ...$arguments): bool
    {
        return self::getInstance()->check($ability, ...$arguments);
    }

    public static function denies(string $ability, mixed ...$arguments): bool
    {
        return !self::allows($ability, ...$arguments);
    }

    public static function authorize(string $ability, mixed ...$arguments): void
    {
        if (self::denies($ability, ...$arguments)) {
            throw new RuntimeException("This action is unauthorized [{$ability}].", 403);
        }
    }

    public function check(string $ability, mixed ...$arguments): bool
    {
        $user = $this->user ?? AuthManager::getInstance()->user();

        // 1. Check direct ability closure
        if (isset($this->abilities[$ability])) {
            return (bool) ($this->abilities[$ability])($user, ...$arguments);
        }

        // 2. Check Class Policy if target object matches
        $firstArg = $arguments[0] ?? null;
        if (is_object($firstArg)) {
            $class = get_class($firstArg);
            if (isset($this->policies[$class])) {
                $policyClass = $this->policies[$class];
                if (class_exists($policyClass) && method_exists($policyClass, $ability)) {
                    $policyInstance = new $policyClass();
                    return (bool) $policyInstance->$ability($user, ...$arguments);
                }
            }
        }

        return false;
    }
}

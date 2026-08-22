<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth;

use InvalidArgumentException;
use Switch\Foundation\Auth\Guard\ApiKeyGuard;
use Switch\Foundation\Auth\Guard\GuardInterface;
use Switch\Foundation\Auth\Guard\SessionGuard;
use Switch\Foundation\Auth\Guard\TokenGuard;

class AuthManager
{
    private static ?self $instance = null;
    private array $config;
    private array $guards = [];
    private array $customDrivers = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default' => 'web',
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                ],
                'api' => [
                    'driver' => 'token',
                    'provider' => 'users',
                    'storage_key' => 'api_token',
                ],
            ],
            'providers' => [
                'users' => [
                    'model' => 'App\\Models\\User',
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

    public function guard(?string $name = null): GuardInterface
    {
        $name ??= $this->getDefaultGuard();

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        return $this->guards[$name] = $this->resolve($name);
    }

    public function extend(string $driver, callable $callback): static
    {
        $this->customDrivers[$driver] = $callback;
        return $this;
    }

    public function getDefaultGuard(): string
    {
        return $this->config['default'] ?? 'web';
    }

    public function setDefaultGuard(string $name): void
    {
        $this->config['default'] = $name;
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->guard()->user();
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function guest(): bool
    {
        return $this->guard()->guest();
    }

    public function id(): mixed
    {
        return $this->guard()->id();
    }

    public function setUser(AuthenticatableInterface $user): static
    {
        $this->guard()->setUser($user);
        return $this;
    }

    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        $guard = $this->guard();
        if (method_exists($guard, 'login')) {
            $guard->login($user, $remember);
        }
    }

    public function logout(): void
    {
        $guard = $this->guard();
        if (method_exists($guard, 'logout')) {
            $guard->logout();
        }
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $guard = $this->guard();
        if (method_exists($guard, 'attempt')) {
            return $guard->attempt($credentials, $remember);
        }
        return false;
    }

    private function resolve(string $name): GuardInterface
    {
        $config = $this->config['guards'][$name] ?? null;

        if ($config === null) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        $driver = $config['driver'] ?? 'session';

        if (isset($this->customDrivers[$driver])) {
            return ($this->customDrivers[$driver])($this, $name, $config);
        }

        $providerName = $config['provider'] ?? 'users';
        $provider = $this->resolveUserProvider($providerName);

        return match ($driver) {
            'session' => new SessionGuard($name, $provider),
            'token' => new TokenGuard($provider, $config['storage_key'] ?? 'api_token'),
            'api_key' => new ApiKeyGuard($provider, $config['header_name'] ?? 'X-API-Key', $config['storage_key'] ?? 'api_key'),
            default => throw new InvalidArgumentException("Auth driver [{$driver}] for guard [{$name}] is not supported."),
        };
    }

    private function resolveUserProvider(string|callable $provider): mixed
    {
        if (is_callable($provider)) {
            return $provider;
        }

        $providerConfig = $this->config['providers'][$provider] ?? null;
        if (is_array($providerConfig) && isset($providerConfig['model'])) {
            return $providerConfig['model'];
        }

        return $provider;
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->guard()->$method(...$parameters);
    }
}

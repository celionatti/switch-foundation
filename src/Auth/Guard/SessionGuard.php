<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Guard;

use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\Hash\HashManager;
use Switch\Session\Session;

class SessionGuard implements GuardInterface
{
    private string $name;
    private $userProvider;
    private ?AuthenticatableInterface $user = null;
    private bool $viaRemember = false;

    /**
     * @param string $name Guard name
     * @param callable|string $userProvider Callback (fn($id) => user) or User model class name
     */
    public function __construct(string $name, $userProvider)
    {
        $this->name = $name;
        $this->userProvider = $userProvider;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $id = $this->getSessionId();

        if ($id !== null) {
            $this->user = $this->retrieveById($id);
        }

        if ($this->user === null) {
            $recId = $this->getRememberMeCookie();
            if ($recId !== null) {
                $user = $this->retrieveById($recId);
                if ($user !== null) {
                    $this->user = $user;
                    $this->viaRemember = true;
                    $this->updateSession($user->getAuthIdentifier());
                }
            }
        }

        return $this->user;
    }

    public function id(): mixed
    {
        if ($this->user()) {
            return $this->user()->getAuthIdentifier();
        }

        return $this->getSessionId();
    }

    public function validate(array $credentials = []): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        $plainPassword = $credentials['password'] ?? '';
        return HashManager::getInstance()->check((string) $plainPassword, $user->getAuthPassword());
    }

    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        $plainPassword = $credentials['password'] ?? '';
        if (HashManager::getInstance()->check((string) $plainPassword, $user->getAuthPassword())) {
            $this->login($user, $remember);
            return true;
        }

        return false;
    }

    public function login(AuthenticatableInterface $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        if ($remember) {
            $this->createRememberMeCookie($user);
        }

        $this->setUser($user);
    }

    public function loginUsingId(mixed $id, bool $remember = false): ?AuthenticatableInterface
    {
        $user = $this->retrieveById($id);

        if ($user !== null) {
            $this->login($user, $remember);
            return $user;
        }

        return null;
    }

    public function logout(): void
    {
        $this->clearSession();
        $this->clearRememberMeCookie();
        $this->user = null;
    }

    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    public function setUser(AuthenticatableInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    protected function getSessionKey(): string
    {
        return 'login_' . $this->name;
    }

    protected function getSessionId(): mixed
    {
        if (class_exists(Session::class)) {
            return Session::get($this->getSessionKey());
        }

        return $_SESSION[$this->getSessionKey()] ?? null;
    }

    protected function updateSession(mixed $id): void
    {
        if (class_exists(Session::class)) {
            Session::put($this->getSessionKey(), $id);
            Session::regenerate();
        } else {
            $_SESSION[$this->getSessionKey()] = $id;
        }
    }

    protected function clearSession(): void
    {
        if (class_exists(Session::class)) {
            Session::forget($this->getSessionKey());
            Session::regenerate(true);
        } else {
            unset($_SESSION[$this->getSessionKey()]);
        }
    }

    protected function getRememberMeCookie(): mixed
    {
        $name = 'remember_' . $this->name;
        return $_COOKIE[$name] ?? null;
    }

    protected function createRememberMeCookie(AuthenticatableInterface $user): void
    {
        $name = 'remember_' . $this->name;
        $value = (string) $user->getAuthIdentifier();
        if (function_exists('cookie')) {
            cookie($name, $value, 60 * 24 * 30); // 30 days
        } else {
            @setcookie($name, $value, time() + (86400 * 30), '/', '', false, true);
        }
    }

    protected function clearRememberMeCookie(): void
    {
        $name = 'remember_' . $this->name;
        if (function_exists('cookie')) {
            cookie($name, '', -86400);
        } else {
            @setcookie($name, '', time() - 86400, '/');
        }
    }

    protected function retrieveById(mixed $id): ?AuthenticatableInterface
    {
        $provider = $this->userProvider;

        if (is_callable($provider)) {
            return $provider($id);
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'find')) {
            return $provider::find($id);
        }

        return null;
    }

    protected function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        $provider = $this->userProvider;

        if (is_callable($provider)) {
            return $provider($credentials);
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'where')) {
            $query = $provider::query();
            foreach ($credentials as $key => $value) {
                if ($key !== 'password') {
                    $query->where($key, '=', $value);
                }
            }
            return $query->first();
        }

        return null;
    }
}

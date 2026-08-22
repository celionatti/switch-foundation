<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Guard;

use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Auth\AuthenticatableInterface;

class TokenGuard implements GuardInterface
{
    private $userProvider;
    private string $storageKey;
    private ?AuthenticatableInterface $user = null;
    private ?ServerRequestInterface $request = null;

    public function __construct($userProvider, string $storageKey = 'api_token')
    {
        $this->userProvider = $userProvider;
        $this->storageKey = $storageKey;
    }

    public function setRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;
        $this->user = null;
        return $this;
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

        $token = $this->getTokenFromRequest();

        if (empty($token)) {
            return null;
        }

        $provider = $this->userProvider;

        if (is_callable($provider)) {
            return $this->user = $provider($token);
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'where')) {
            return $this->user = $provider::where($this->storageKey, '=', $token)->first();
        }

        return null;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $token = $credentials[$this->storageKey] ?? $credentials['token'] ?? null;
        if (empty($token)) {
            return false;
        }

        $provider = $this->userProvider;
        if (is_callable($provider)) {
            return $provider($token) !== null;
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'where')) {
            return $provider::where($this->storageKey, '=', $token)->exists();
        }

        return false;
    }

    public function setUser(AuthenticatableInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    protected function getTokenFromRequest(): ?string
    {
        if ($this->request !== null) {
            $header = $this->request->getHeaderLine('Authorization');
            if (str_starts_with($header, 'Bearer ')) {
                return substr($header, 7);
            }

            $queryParams = $this->request->getQueryParams();
            if (isset($queryParams[$this->storageKey])) {
                return (string) $queryParams[$this->storageKey];
            }
        }

        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return $_GET[$this->storageKey] ?? null;
    }
}

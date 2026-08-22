<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Guard;

use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Auth\AuthenticatableInterface;

class ApiKeyGuard implements GuardInterface
{
    private $userProvider;
    private string $headerName;
    private string $storageKey;
    private ?AuthenticatableInterface $user = null;
    private ?ServerRequestInterface $request = null;

    public function __construct($userProvider, string $headerName = 'X-API-Key', string $storageKey = 'api_key')
    {
        $this->userProvider = $userProvider;
        $this->headerName = $headerName;
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

        $apiKey = $this->getApiKeyFromRequest();

        if (empty($apiKey)) {
            return null;
        }

        $provider = $this->userProvider;

        if (is_callable($provider)) {
            return $this->user = $provider($apiKey);
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'where')) {
            return $this->user = $provider::where($this->storageKey, '=', $apiKey)->first();
        }

        return null;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        $key = $credentials[$this->storageKey] ?? $credentials['key'] ?? null;
        if (empty($key)) {
            return false;
        }

        $provider = $this->userProvider;
        if (is_callable($provider)) {
            return $provider($key) !== null;
        }

        if (is_string($provider) && class_exists($provider) && method_exists($provider, 'where')) {
            return $provider::where($this->storageKey, '=', $key)->exists();
        }

        return false;
    }

    public function setUser(AuthenticatableInterface $user): static
    {
        $this->user = $user;
        return $this;
    }

    protected function getApiKeyFromRequest(): ?string
    {
        if ($this->request !== null) {
            if ($this->request->hasHeader($this->headerName)) {
                return $this->request->getHeaderLine($this->headerName);
            }

            $queryParams = $this->request->getQueryParams();
            if (isset($queryParams[$this->storageKey])) {
                return (string) $queryParams[$this->storageKey];
            }
        }

        $serverHeader = 'HTTP_' . strtoupper(str_replace('-', '_', $this->headerName));
        if (!empty($_SERVER[$serverHeader])) {
            return (string) $_SERVER[$serverHeader];
        }

        return $_GET[$this->storageKey] ?? null;
    }
}

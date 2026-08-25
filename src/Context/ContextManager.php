<?php

declare(strict_types=1);

namespace Switch\Foundation\Context;

use Closure;

class ContextManager
{
    /**
     * @var array<string, Context> Active named contexts
     */
    private array $contexts = [];

    /**
     * @var array<string, bool> Flag marking contexts meant for client frontend hydration
     */
    private array $clientContexts = [];

    /**
     * Create or retrieve a named Context instance.
     */
    public function context(string $name, mixed $default = null): Context
    {
        if (!isset($this->contexts[$name])) {
            $this->contexts[$name] = new Context($name, $default);
        }

        return $this->contexts[$name];
    }

    /**
     * Provide a value to a named Context. If callback given, scope is isolated.
     *
     * @template T
     * @param string $name
     * @param mixed $value
     * @param (callable(mixed, Context): T)|null $callback
     * @return ($callback is null ? Context : T)
     */
    public function provide(string $name, mixed $value, ?callable $callback = null): mixed
    {
        $ctx = $this->context($name);

        if ($callback === null) {
            $ctx->set($value);
            return $ctx;
        }

        return $ctx->provide($value, $callback);
    }

    /**
     * Provide multiple contexts simultaneously for a callback scope.
     *
     * @template T
     * @param array<string, mixed> $contexts Key-value pairs of context names and values
     * @param (callable(): T)|null $callback
     * @return ($callback is null ? self : T)
     */
    public function provideMany(array $contexts, ?callable $callback = null): mixed
    {
        if (empty($contexts)) {
            return $callback !== null ? $callback() : $this;
        }

        if ($callback === null) {
            foreach ($contexts as $name => $value) {
                $this->provide($name, $value);
            }
            return $this;
        }

        // Nest scopes recursively
        $keys = array_keys($contexts);
        $name = array_shift($keys);
        $value = $contexts[$name];

        $remaining = [];
        foreach ($keys as $k) {
            $remaining[$k] = $contexts[$k];
        }

        return $this->provide($name, $value, function () use ($remaining, $callback) {
            return $this->provideMany($remaining, $callback);
        });
    }

    /**
     * Consume/use a context value by name, with dot notation support.
     * e.g. `use('theme')` or `use('user.profile.avatar', '/default.jpg')`
     */
    public function use(string $name, mixed $default = null): mixed
    {
        if (str_contains($name, '.')) {
            [$root, $rest] = explode('.', $name, 2);
            if (!isset($this->contexts[$root])) {
                return $default;
            }
            return $this->contexts[$root]->get($rest, $default);
        }

        if (!isset($this->contexts[$name])) {
            return $default;
        }

        return $this->contexts[$name]->value($default);
    }

    /**
     * Mutate a context state via callback function.
     */
    public function mutate(string $name, callable $mutator): mixed
    {
        return $this->context($name)->mutate($mutator);
    }

    /**
     * Merge an array of data into a named context.
     */
    public function merge(string $name, array $data): Context
    {
        return $this->context($name)->merge($data);
    }

    /**
     * Subscribe to changes on a named context.
     */
    public function subscribe(string $name, callable $listener): Closure
    {
        return $this->context($name)->subscribe($listener);
    }

    /**
     * Check if a named context exists.
     */
    public function has(string $name): bool
    {
        if (str_contains($name, '.')) {
            [$root, $rest] = explode('.', $name, 2);
            return isset($this->contexts[$root]) && $this->contexts[$root]->has($rest);
        }

        return isset($this->contexts[$name]);
    }

    /**
     * Mark a context to be synchronized with the frontend Switch Live client.
     */
    public function markClient(string $name, bool $sync = true): self
    {
        $this->clientContexts[$name] = $sync;
        return $this;
    }

    /**
     * Get all client-synchronized context states as an associative array.
     *
     * @return array<string, mixed>
     */
    public function getClientPayload(): array
    {
        $payload = [];
        foreach ($this->clientContexts as $name => $sync) {
            if ($sync && isset($this->contexts[$name])) {
                $payload[$name] = $this->contexts[$name]->value();
            }
        }
        return $payload;
    }

    /**
     * Get all registered contexts.
     *
     * @return array<string, Context>
     */
    public function all(): array
    {
        return $this->contexts;
    }

    /**
     * Clear all registered contexts (for testing teardown).
     */
    public function clear(): void
    {
        $this->contexts = [];
        $this->clientContexts = [];
    }
}

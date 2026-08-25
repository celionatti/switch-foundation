<?php

declare(strict_types=1);

namespace Switch\Foundation\Context;

use JsonSerializable;
use Closure;

class Context implements JsonSerializable
{
    private string $name;

    /**
     * @var array<int, mixed> Stack of scoped values (top of stack is active)
     */
    private array $stack = [];

    /**
     * @var array<string, callable> Registered state mutation subscribers
     */
    private array $subscribers = [];

    private mixed $defaultValue;

    public function __construct(string $name, mixed $defaultValue = null)
    {
        $this->name = $name;
        $this->defaultValue = $defaultValue;
        $this->stack = [$defaultValue];
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the current context value (top of stack).
     */
    public function value(mixed $default = null): mixed
    {
        if (empty($this->stack)) {
            return $default ?? $this->defaultValue;
        }

        $current = end($this->stack);
        return $current ?? ($default ?? $this->defaultValue);
    }

    /**
     * Get a specific key using dot-notation.
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        $val = $this->value();

        if ($key === null || $key === '') {
            return $val ?? $default;
        }

        if (!is_array($val) && !is_object($val)) {
            return $default;
        }

        $segments = explode('.', $key);
        $target = $val;

        foreach ($segments as $segment) {
            if (is_array($target) && array_key_exists($segment, $target)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } elseif (is_object($target) && method_exists($target, $segment)) {
                $target = $target->{$segment}();
            } else {
                return $default;
            }
        }

        return $target;
    }

    /**
     * Set/replace the current context value at top of stack.
     */
    public function set(mixed $value): self
    {
        $oldValue = $this->value();

        if (empty($this->stack)) {
            $this->stack = [$value];
        } else {
            $key = array_key_last($this->stack);
            $this->stack[$key] = $value;
        }

        $this->notify($value, $oldValue);
        return $this;
    }

    /**
     * Update a specific dot-notation key in the current value.
     */
    public function setKey(string $key, mixed $value): self
    {
        $current = $this->value();
        if (!is_array($current)) {
            $current = is_object($current) ? (array) $current : [];
        }

        $keys = explode('.', $key);
        $temp = &$current;

        foreach ($keys as $segment) {
            if (!isset($temp[$segment]) || !is_array($temp[$segment])) {
                $temp[$segment] = [];
            }
            $temp = &$temp[$segment];
        }

        $temp = $value;
        $this->set($current);
        return $this;
    }

    /**
     * Merge an array into the current context state.
     */
    public function merge(array $data): self
    {
        $current = $this->value();
        if (!is_array($current)) {
            $current = [];
        }

        $merged = array_replace_recursive($current, $data);
        return $this->set($merged);
    }

    /**
     * Mutate the state via a callback function: fn($prevState) => $newState
     */
    public function mutate(callable $mutator): mixed
    {
        $current = $this->value();
        $newVal = $mutator($current);
        $this->set($newVal);
        return $newVal;
    }

    /**
     * Check if key or state exists and is not null.
     */
    public function has(?string $key = null): bool
    {
        if ($key === null) {
            return $this->value() !== null;
        }

        return $this->get($key) !== null;
    }

    /**
     * Push a new scoped value onto the context stack (Provider boundary).
     */
    public function push(mixed $value): self
    {
        $this->stack[] = $value;
        $this->notify($value, null);
        return $this;
    }

    /**
     * Pop the current scoped value off the context stack (exiting Provider).
     */
    public function pop(): mixed
    {
        if (count($this->stack) > 1) {
            $popped = array_pop($this->stack);
            $this->notify($this->value(), $popped);
            return $popped;
        }

        return $this->value();
    }

    /**
     * Provide a scoped value for the duration of a callback, then automatically restore previous state.
     *
     * @template T
     * @param mixed $value
     * @param callable(): T $callback
     * @return T
     */
    public function provide(mixed $value, ?callable $callback = null): mixed
    {
        $this->push($value);

        if ($callback === null) {
            return $this;
        }

        try {
            return $callback($value, $this);
        } finally {
            $this->pop();
        }
    }

    /**
     * Subscribe to state changes on this context.
     * Returns an un-subscriber closure.
     */
    public function subscribe(callable $listener): Closure
    {
        $id = uniqid('sub_', true);
        $this->subscribers[$id] = $listener;

        return function () use ($id): void {
            unset($this->subscribers[$id]);
        };
    }

    /**
     * Notify all registered subscribers of a value change.
     */
    private function notify(mixed $newValue, mixed $oldValue): void
    {
        foreach ($this->subscribers as $listener) {
            try {
                $listener($newValue, $oldValue, $this);
            } catch (\Throwable) {
                // Keep notification robust against subscriber exceptions
            }
        }
    }

    public function jsonSerialize(): mixed
    {
        return $this->value();
    }

    public function toJson(int $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->value(), $options) ?: '{}';
    }

    public function __toString(): string
    {
        $val = $this->value();
        if (is_scalar($val)) {
            return (string) $val;
        }
        return $this->toJson();
    }
}

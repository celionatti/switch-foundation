<?php

declare(strict_types=1);

namespace Switch\Foundation\Collection;

/**
 * Proxy for higher-order collection messaging.
 */
class HigherOrderCollectionProxy
{
    public function __construct(
        protected Enumerable $collection,
        protected string $method
    ) {
    }

    /**
     * Proxy accessing an item property.
     */
    public function __get(string $key): mixed
    {
        return $this->collection->{$this->method}(function ($value) use ($key) {
            return is_array($value) ? ($value[$key] ?? null) : ($value->{$key} ?? null);
        });
    }

    /**
     * Proxy calling an item method.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->collection->{$this->method}(function ($value) use ($method, $parameters) {
            return $value->{$method}(...$parameters);
        });
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Api;

use ArrayAccess;
use JsonSerializable;

class JsonResource implements JsonSerializable, ArrayAccess
{
    /** @var mixed */
    public mixed $resource;

    public function __construct(mixed $resource)
    {
        $this->resource = $resource;
    }

    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    public static function collection(iterable $resources): ResourceCollection
    {
        return new ResourceCollection($resources, static::class);
    }

    /**
     * Transform the resource into an array.
     */
    public function toArray(): array
    {
        if (is_null($this->resource)) {
            return [];
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (is_object($this->resource) && method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }

        return (array) $this->resource;
    }

    public function resolve(): array
    {
        $data = $this->toArray();

        // Filter out MissingValue instances
        return array_filter($data, fn($val) => !($val instanceof MissingValue));
    }

    public function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return is_callable($value) ? $value() : $value;
        }

        return $default !== null ? (is_callable($default) ? $default() : $default) : new MissingValue();
    }

    public function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        $isLoaded = false;
        if (is_object($this->resource)) {
            if (method_exists($this->resource, 'relationLoaded')) {
                $isLoaded = $this->resource->relationLoaded($relationship);
            } elseif (isset($this->resource->{$relationship})) {
                $isLoaded = true;
            }
        }

        if ($isLoaded) {
            if ($value !== null) {
                return is_callable($value) ? $value() : $value;
            }
            return $this->resource->{$relationship} ?? null;
        }

        return $default !== null ? (is_callable($default) ? $default() : $default) : new MissingValue();
    }

    public function jsonSerialize(): array
    {
        return $this->resolve();
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_array($this->resource) ? isset($this->resource[$offset]) : isset($this->resource->{$offset});
    }

    public function offsetGet(mixed $offset): mixed
    {
        return is_array($this->resource) ? ($this->resource[$offset] ?? null) : ($this->resource->{$offset} ?? null);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_array($this->resource)) {
            $this->resource[$offset] = $value;
        } else {
            $this->resource->{$offset} = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        if (is_array($this->resource)) {
            unset($this->resource[$offset]);
        } else {
            unset($this->resource->{$offset});
        }
    }

    public function __get(string $name): mixed
    {
        return is_array($this->resource) ? ($this->resource[$name] ?? null) : ($this->resource->{$name} ?? null);
    }
}

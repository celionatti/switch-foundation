<?php

declare(strict_types=1);

namespace Switch\Foundation\Collection;

use ArrayIterator;
use Closure;
use Generator;
use IteratorAggregate;
use Traversable;

/**
 * High-Performance, Low-Memory Generator-Backed Lazy Collection Stream.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements Enumerable<TKey, TValue>
 */
class LazyCollection implements Enumerable
{
    /**
     * The source generator or generator provider closure.
     *
     * @var Closure|Generator|array
     */
    protected mixed $source;

    /**
     * Create a new LazyCollection instance.
     */
    public function __construct(mixed $source = null)
    {
        if ($source instanceof Closure) {
            $this->source = $source;
        } elseif ($source instanceof Generator) {
            $this->source = $source;
        } elseif (is_array($source)) {
            $this->source = static::getGeneratorFromArray($source);
        } elseif ($source instanceof Enumerable) {
            $this->source = static::getGeneratorFromArray($source->all());
        } elseif ($source === null) {
            $this->source = static::getGeneratorFromArray([]);
        } else {
            $this->source = static::getGeneratorFromArray((array) $source);
        }
    }

    /**
     * Create a new LazyCollection instance.
     */
    public static function make(mixed $source = null): static
    {
        return new static($source);
    }

    /**
     * Create a new LazyCollection generating numbers lazily.
     */
    public static function times(int $number, ?callable $callback = null): static
    {
        return new static(function () use ($number, $callback) {
            for ($i = 1; $i <= $number; $i++) {
                yield $i => $callback ? $callback($i) : $i;
            }
        });
    }

    /**
     * Create a new LazyCollection from a range.
     */
    public static function range(int|float $from, int|float $to, int|float $step = 1): static
    {
        return new static(function () use ($from, $to, $step) {
            for ($i = $from; $step > 0 ? $i <= $to : $i >= $to; $i += $step) {
                yield $i;
            }
        });
    }

    /**
     * Create a new LazyCollection by repeatedly invoking the given callback until it returns null.
     */
    public static function generate(callable $callback): static
    {
        return new static(function () use ($callback) {
            while (($result = $callback()) !== null) {
                yield $result;
            }
        });
    }

    /**
     * Convert LazyCollection into an eager Collection.
     */
    public function eager(): Collection
    {
        return new Collection($this->all());
    }

    /**
     * Get all items as an array.
     */
    public function all(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    public function filter(?callable $callback = null): static
    {
        return new static(function () use ($callback) {
            foreach ($this->getIterator() as $key => $value) {
                if ($callback ? $callback($value, $key) : (bool) $value) {
                    yield $key => $value;
                }
            }
        });
    }

    public function reject(callable $callback): static
    {
        return $this->filter(fn($value, $key) => !$callback($value, $key));
    }

    public function take(int $limit): static
    {
        return new static(function () use ($limit) {
            if ($limit <= 0) {
                return;
            }

            $count = 0;
            foreach ($this->getIterator() as $key => $value) {
                yield $key => $value;
                $count++;
                if ($count >= $limit) {
                    break;
                }
            }
        });
    }

    public function skip(int $count): static
    {
        return new static(function () use ($count) {
            $skipped = 0;
            foreach ($this->getIterator() as $key => $value) {
                if ($skipped < $count) {
                    $skipped++;
                    continue;
                }
                yield $key => $value;
            }
        });
    }

    public function chunk(int $size): static
    {
        return new static(function () use ($size) {
            $chunk = [];
            foreach ($this->getIterator() as $key => $value) {
                $chunk[$key] = $value;
                if (count($chunk) >= $size) {
                    yield new Collection($chunk);
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
                yield new Collection($chunk);
            }
        });
    }

    public function pluck(string $value, ?string $key = null): static
    {
        return new static(function () use ($value, $key) {
            foreach ($this->getIterator() as $item) {
                $itemValue = is_array($item) ? ($item[$value] ?? null) : ($item->{$value} ?? null);
                if ($key === null) {
                    yield $itemValue;
                } else {
                    $itemKey = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
                    yield (string) $itemKey => $itemValue;
                }
            }
        });
    }

    public function values(): static
    {
        return new static(function () {
            foreach ($this->getIterator() as $value) {
                yield $value;
            }
        });
    }

    public function unique(?string $key = null, bool $strict = false): static
    {
        return new static(function () use ($key, $strict) {
            $exists = [];
            foreach ($this->getIterator() as $k => $item) {
                $val = $key ? (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null)) : $item;
                if (!in_array($val, $exists, $strict)) {
                    $exists[] = $val;
                    yield $k => $item;
                }
            }
        });
    }

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this->getIterator() as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default instanceof Closure ? $default() : $default;
    }

    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        return $this->eager()->last($callback, $default);
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function isEmpty(): bool
    {
        return !$this->getIterator()->valid();
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function sum(?string $key = null): float|int
    {
        return $this->eager()->sum($key);
    }

    public function avg(?string $key = null): float|int|null
    {
        return $this->eager()->avg($key);
    }

    public function average(?string $key = null): float|int|null
    {
        return $this->avg($key);
    }

    public function min(?string $key = null): mixed
    {
        return $this->eager()->min($key);
    }

    public function max(?string $key = null): mixed
    {
        return $this->eager()->max($key);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $result = $initial;
        foreach ($this->getIterator() as $key => $value) {
            $result = $callback($result, $value, $key);
        }
        return $result;
    }

    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    public function collapse(): static
    {
        return new static(function () {
            foreach ($this->getIterator() as $values) {
                if ($values instanceof Enumerable || is_array($values)) {
                    foreach ($values as $item) {
                        yield $item;
                    }
                }
            }
        });
    }

    public function flatten(?int $depth = null): static
    {
        return $this->eager()->flatten($depth)->lazy();
    }

    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): static
    {
        return $this->eager()->groupBy($groupBy, $preserveKeys)->lazy();
    }

    public function reverse(): static
    {
        return $this->eager()->reverse()->lazy();
    }

    public function slice(int $offset, ?int $length = null): static
    {
        return $this->skip($offset)->take($length ?? PHP_INT_MAX);
    }

    public function sort(?callable $callback = null): static
    {
        return $this->eager()->sort($callback)->lazy();
    }

    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        return $this->eager()->sortBy($callback, $options, $descending)->lazy();
    }

    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    public function contains(mixed $key, mixed $value = null): bool
    {
        return $this->first(func_num_args() === 1 && is_callable($key) ? $key : fn($item) => $item == $key) !== null;
    }

    public function diff(mixed $items): static
    {
        return $this->eager()->diff($items)->lazy();
    }

    public function where(string $key, mixed $operator = null, mixed $value = null): static
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->filter(function ($item) use ($key, $operator, $value) {
            $retrieved = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return match (strtolower((string) $operator)) {
                '=' => $retrieved == $value,
                '!=' => $retrieved != $value,
                '>' => $retrieved > $value,
                '<' => $retrieved < $value,
                '>=' => $retrieved >= $value,
                '<=' => $retrieved <= $value,
                default => $retrieved == $value,
            };
        });
    }

    public function whereIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $values instanceof Enumerable ? $values->all() : (array) $values;
        return $this->filter(function ($item) use ($key, $values, $strict) {
            $val = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return in_array($val, $values, $strict);
        });
    }

    public function whereNotIn(string $key, mixed $values, bool $strict = false): static
    {
        $values = $values instanceof Enumerable ? $values->all() : (array) $values;
        return $this->filter(function ($item) use ($key, $values, $strict) {
            $val = is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null);
            return !in_array($val, $values, $strict);
        });
    }

    public function whereNull(string $key): static
    {
        return $this->filter(fn($item) => (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null)) === null);
    }

    public function whereNotNull(string $key): static
    {
        return $this->filter(fn($item) => (is_array($item) ? ($item[$key] ?? null) : ($item->{$key} ?? null)) !== null);
    }

    public function toArray(): array
    {
        return $this->eager()->toArray();
    }

    public function toJson(int $options = 0): string
    {
        return $this->eager()->toJson($options);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function getIterator(): Traversable
    {
        if ($this->source instanceof Closure) {
            return ($this->source)();
        }

        return $this->source;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->eager()->offsetExists($offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->eager()->offsetGet($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Cannot mutate LazyCollection via ArrayAccess directly.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Cannot mutate LazyCollection via ArrayAccess directly.');
    }

    protected static function getGeneratorFromArray(array $items): Generator
    {
        foreach ($items as $key => $value) {
            yield $key => $value;
        }
    }
}

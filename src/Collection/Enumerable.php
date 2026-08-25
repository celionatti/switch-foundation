<?php

declare(strict_types=1);

namespace Switch\Foundation\Collection;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;

/**
 * Enumerable Contract for Eager and Lazy Collections.
 *
 * @template TKey of array-key
 * @template TValue
 * @extends ArrayAccess<TKey, TValue>
 * @extends IteratorAggregate<TKey, TValue>
 */
interface Enumerable extends ArrayAccess, Countable, IteratorAggregate, JsonSerializable, Stringable
{
    public function all(): array;

    public function avg(?string $key = null): float|int|null;

    public function average(?string $key = null): float|int|null;

    public function chunk(int $size): self;

    public function collapse(): self;

    public function contains(mixed $key, mixed $value = null): bool;

    public function count(): int;

    public function diff(mixed $items): self;

    public function filter(?callable $callback = null): self;

    public function first(?callable $callback = null, mixed $default = null): mixed;

    public function flatMap(callable $callback): self;

    public function flatten(?int $depth = null): self;

    public function groupBy(string|callable $groupBy, bool $preserveKeys = false): self;

    public function isEmpty(): bool;

    public function isNotEmpty(): bool;

    public function last(?callable $callback = null, mixed $default = null): mixed;

    public function map(callable $callback): self;

    public function max(?string $key = null): mixed;

    public function min(?string $key = null): mixed;

    public function pluck(string $value, ?string $key = null): self;

    public function reduce(callable $callback, mixed $initial = null): mixed;

    public function reject(callable $callback): self;

    public function reverse(): self;

    public function slice(int $offset, ?int $length = null): self;

    public function sort(?callable $callback = null): self;

    public function sortBy(string|callable $callback, int $options = SORT_REGULAR, bool $descending = false): self;

    public function sortByDesc(string|callable $callback, int $options = SORT_REGULAR): self;

    public function sum(?string $key = null): float|int;

    public function take(int $limit): self;

    public function toArray(): array;

    public function toJson(int $options = 0): string;

    public function unique(?string $key = null, bool $strict = false): self;

    public function values(): self;

    public function where(string $key, mixed $operator = null, mixed $value = null): self;

    public function whereIn(string $key, mixed $values, bool $strict = false): self;

    public function whereNotIn(string $key, mixed $values, bool $strict = false): self;

    public function whereNull(string $key): self;

    public function whereNotNull(string $key): self;
}

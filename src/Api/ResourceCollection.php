<?php

declare(strict_types=1);

namespace Switch\Foundation\Api;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

class ResourceCollection implements JsonSerializable, Countable, IteratorAggregate
{
    private iterable $collection;
    private string $collects;
    private array $additional = [];

    public function __construct(iterable $collection, string $collects)
    {
        $this->collection = $collection;
        $this->collects = $collects;
    }

    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    public function toArray(): array
    {
        $data = [];
        $class = $this->collects;

        foreach ($this->collection as $item) {
            $data[] = (new $class($item))->resolve();
        }

        return $data;
    }

    public function resolve(): array
    {
        $payload = ['data' => $this->toArray()];
        if (!empty($this->additional)) {
            $payload = array_merge($payload, $this->additional);
        }
        return $payload;
    }

    public function jsonSerialize(): array
    {
        return $this->resolve();
    }

    public function count(): int
    {
        return is_countable($this->collection) ? count($this->collection) : iterator_count($this->getIterator());
    }

    public function getIterator(): Traversable
    {
        if ($this->collection instanceof Traversable) {
            return $this->collection;
        }

        return new \ArrayIterator((array) $this->collection);
    }
}

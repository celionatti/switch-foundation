<?php

declare(strict_types=1);

namespace Switch\Foundation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Cached
{
    /**
     * @param int $ttl Cache time to live in seconds (default 300 / 5 minutes)
     * @param array<int, string> $tags Cache invalidation tags
     * @param string|null $key Optional custom cache key
     */
    public function __construct(
        public int $ttl = 300,
        public array $tags = [],
        public ?string $key = null
    ) {
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class RateLimit
{
    /**
     * @param int|string $limit Maximum attempts or rate expression (e.g. 60 or '60/minute')
     * @param int $decaySeconds Decay window in seconds (default 60)
     * @param string|null $by Key identifier for rate limiting (ip, user, or custom parameter)
     */
    public function __construct(
        public int|string $limit = 60,
        public int $decaySeconds = 60,
        public ?string $by = null
    ) {
    }
}

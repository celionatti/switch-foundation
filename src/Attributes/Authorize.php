<?php

declare(strict_types=1);

namespace Switch\Foundation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Authorize
{
    /**
     * @param string $ability The gate ability or policy method name
     * @param array $arguments Arguments passed to gate/policy check
     */
    public function __construct(
        public string $ability,
        public array $arguments = []
    ) {
    }
}

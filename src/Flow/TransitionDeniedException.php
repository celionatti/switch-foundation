<?php

declare(strict_types=1);

namespace Switch\Foundation\Flow;

use RuntimeException;

class TransitionDeniedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $fromState = '',
        public readonly string $toState = '',
        public readonly string $transition = ''
    ) {
        parent::__construct($message);
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless\Exception;

class TooManyRequestsException extends PasswordlessException
{
    public function __construct(
        string $message = 'Too many requests. Please try again later.',
        public readonly int $availableInSeconds = 60,
        int $code = 429,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

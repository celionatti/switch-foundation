<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth;

interface AuthenticatableInterface
{
    public function getAuthIdentifierName(): string;

    public function getAuthIdentifier(): mixed;

    public function getAuthPassword(): string;

    public function getRememberToken(): ?string;

    public function setRememberToken(?string $value): void;

    public function getRememberTokenName(): string;
}

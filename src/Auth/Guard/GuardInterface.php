<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Guard;

use Switch\Foundation\Auth\AuthenticatableInterface;

interface GuardInterface
{
    public function check(): bool;

    public function guest(): bool;

    public function user(): ?AuthenticatableInterface;

    public function id(): mixed;

    public function validate(array $credentials = []): bool;

    public function setUser(AuthenticatableInterface $user): static;
}

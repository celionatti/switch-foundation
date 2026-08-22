<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Access;

trait AuthorizableTrait
{
    public function can(string $ability, mixed ...$arguments): bool
    {
        return Gate::forUser($this)->check($ability, ...$arguments);
    }

    public function cannot(string $ability, mixed ...$arguments): bool
    {
        return !$this->can($ability, ...$arguments);
    }
}

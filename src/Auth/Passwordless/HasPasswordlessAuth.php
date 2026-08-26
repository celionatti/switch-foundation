<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless;

/**
 * Trait HasPasswordlessAuth
 *
 * Implements AuthenticatableInterface without requiring passwords and provides
 * convenience methods for passwordless magic links.
 */
trait HasPasswordlessAuth
{
    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey ?? 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * Get the password for the user (empty string for passwordless users).
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->password ?? '');
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return $this->remember_token ?? null;
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken(?string $value): void
    {
        $this->remember_token = $value;
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Retrieve a user instance by their email address.
     */
    public static function findByEmail(string $email): ?static
    {
        return static::where('email', '=', $email)->first();
    }

    /**
     * Send a magic login link to this user's email address.
     */
    public function sendLoginLink(?int $expiresInMinutes = null): bool|string|int
    {
        return PasswordlessManager::getInstance()->sendLoginLink(
            (string) $this->email,
            $expiresInMinutes
        );
    }

    /**
     * Send an account recovery link to this user's email address.
     */
    public function sendRecoveryLink(?int $expiresInMinutes = null): bool|string|int
    {
        return PasswordlessManager::getInstance()->sendRecoveryLink(
            (string) $this->email,
            $expiresInMinutes
        );
    }
}

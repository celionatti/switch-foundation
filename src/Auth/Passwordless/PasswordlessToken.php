<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless;

use Switch\Database\ORM\Model;

/**
 * PasswordlessToken Model
 *
 * Stores magic link and recovery tokens.
 *
 * @property int $id
 * @property string $email
 * @property string $token
 * @property string $type 'login', 'register', 'recovery'
 * @property array|null $payload Serialized data for pending registration/context
 * @property string $expires_at
 * @property string|null $used_at
 * @property string $created_at
 * @property string $updated_at
 */
class PasswordlessToken extends Model
{
    public const TYPE_LOGIN = 'login';
    public const TYPE_REGISTER = 'register';
    public const TYPE_RECOVERY = 'recovery';

    protected string $table = 'passwordless_tokens';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'email',
        'token',
        'type',
        'payload',
        'expires_at',
        'used_at',
    ];

    protected array $casts = [
        'payload' => 'json',
    ];

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        if (empty($this->expires_at)) {
            return true;
        }

        return strtotime((string) $this->expires_at) <= time();
    }

    /**
     * Check if the token has already been used.
     */
    public function isUsed(): bool
    {
        return !empty($this->used_at);
    }

    /**
     * Check if the token is currently valid (not used and not expired).
     */
    public function isValid(): bool
    {
        return !$this->isUsed() && !$this->isExpired();
    }

    /**
     * Mark the token as used.
     */
    public function markUsed(): void
    {
        $this->used_at = date('Y-m-d H:i:s');
        $this->save();
    }
}

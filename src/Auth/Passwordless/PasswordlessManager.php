<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless;

use InvalidArgumentException;
use Switch\Foundation\Api\RateLimiter;
use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Passwordless\Exception\InvalidTokenException;
use Switch\Foundation\Auth\Passwordless\Exception\TokenExpiredException;
use Switch\Foundation\Auth\Passwordless\Exception\TooManyRequestsException;
use Switch\Foundation\Auth\Passwordless\Exception\UserNotFoundException;
use Switch\Foundation\Auth\Passwordless\Mail\MagicLinkMailable;
use Switch\Foundation\Mailer\MailManager;

class PasswordlessManager
{
    private static ?self $instance = null;
    private array $config;
    private RateLimiter $rateLimiter;
    private MailManager $mailManager;

    public function __construct(
        array $config = [],
        ?RateLimiter $rateLimiter = null,
        ?MailManager $mailManager = null
    ) {
        $this->config = array_merge([
            'token_expiry' => 15,          // minutes for login & register
            'recovery_expiry' => 60,       // minutes for account recovery
            'token_length' => 64,          // characters
            'app_url' => function_exists('env') ? env('APP_URL', 'http://localhost:8000') : 'http://localhost:8000',
            'app_name' => function_exists('env') ? env('APP_NAME', 'Switch Framework') : 'Switch Framework',
            'verify_route' => '/auth/verify',
            'user_model' => 'App\\Models\\User',
            'rate_limit' => [
                'enabled' => true,
                'max_attempts' => 5,       // Max 5 attempts per window
                'decay_seconds' => 3600,   // 1 hour window
            ],
            'auto_register' => false,      // If true, login with unknown email creates account
        ], $config);

        $this->rateLimiter = $rateLimiter ?? RateLimiter::getInstance();
        $this->mailManager = $mailManager ?? MailManager::getInstance();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config[$key] ?? $default;
    }

    public function setConfig(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->config = array_merge($this->config, $key);
        } else {
            $this->config[$key] = $value;
        }

        return $this;
    }

    /**
     * Send a magic login link to the given email address.
     *
     * @throws TooManyRequestsException if rate limit is exceeded
     * @throws UserNotFoundException if user doesn't exist and auto_register is false
     */
    public function sendLoginLink(string $email, ?int $expiresInMinutes = null): bool|string|int
    {
        $this->enforceRateLimit("passwordless:login:{$email}");

        $userModel = $this->getUserModel();
        $user = null;

        if (class_exists($userModel) && method_exists($userModel, 'where')) {
            $user = $userModel::where('email', '=', $email)->first();
        }

        if ($user === null && empty($this->config['auto_register'])) {
            throw new UserNotFoundException("No registered account found with email '{$email}'.");
        }

        $expiresInMinutes ??= (int) $this->config['token_expiry'];
        $token = $this->generateToken($email, PasswordlessToken::TYPE_LOGIN, [], $expiresInMinutes);
        $verifyUrl = $this->buildVerifyUrl($token->token);

        $mailable = new MagicLinkMailable(
            verifyUrl: $verifyUrl,
            type: PasswordlessToken::TYPE_LOGIN,
            expiresInMinutes: $expiresInMinutes,
            appName: (string) $this->config['app_name']
        );
        $mailable->to($email);

        return $this->mailManager->send($mailable);
    }

    /**
     * Send a registration confirmation link.
     *
     * @throws TooManyRequestsException if rate limit is exceeded
     */
    public function sendRegistrationLink(string $email, array $userData = [], ?int $expiresInMinutes = null): bool|string|int
    {
        $this->enforceRateLimit("passwordless:register:{$email}");

        $expiresInMinutes ??= (int) $this->config['token_expiry'];
        $token = $this->generateToken($email, PasswordlessToken::TYPE_REGISTER, $userData, $expiresInMinutes);
        $verifyUrl = $this->buildVerifyUrl($token->token);

        $mailable = new MagicLinkMailable(
            verifyUrl: $verifyUrl,
            type: PasswordlessToken::TYPE_REGISTER,
            expiresInMinutes: $expiresInMinutes,
            appName: (string) $this->config['app_name']
        );
        $mailable->to($email);

        return $this->mailManager->send($mailable);
    }

    /**
     * Send an account recovery magic link.
     *
     * @throws TooManyRequestsException if rate limit is exceeded
     * @throws UserNotFoundException if user doesn't exist
     */
    public function sendRecoveryLink(string $email, ?int $expiresInMinutes = null): bool|string|int
    {
        $this->enforceRateLimit("passwordless:recovery:{$email}");

        $userModel = $this->getUserModel();
        $user = null;

        if (class_exists($userModel) && method_exists($userModel, 'where')) {
            $user = $userModel::where('email', '=', $email)->first();
        }

        if ($user === null) {
            throw new UserNotFoundException("No registered account found with email '{$email}'.");
        }

        $expiresInMinutes ??= (int) $this->config['recovery_expiry'];
        $token = $this->generateToken($email, PasswordlessToken::TYPE_RECOVERY, [], $expiresInMinutes);
        $verifyUrl = $this->buildVerifyUrl($token->token);

        $mailable = new MagicLinkMailable(
            verifyUrl: $verifyUrl,
            type: PasswordlessToken::TYPE_RECOVERY,
            expiresInMinutes: $expiresInMinutes,
            appName: (string) $this->config['app_name']
        );
        $mailable->to($email);

        return $this->mailManager->send($mailable);
    }

    /**
     * Generate and store a new passwordless token.
     */
    public function generateToken(
        string $email,
        string $type = PasswordlessToken::TYPE_LOGIN,
        array $payload = [],
        ?int $expiresInMinutes = null
    ): PasswordlessToken {
        $length = (int) ($this->config['token_length'] ?? 64);
        $rawToken = bin2hex(random_bytes(max(16, (int) ($length / 2))));

        $expiresInMinutes ??= ($type === PasswordlessToken::TYPE_RECOVERY)
            ? (int) $this->config['recovery_expiry']
            : (int) $this->config['token_expiry'];

        $expiresAt = date('Y-m-d H:i:s', time() + ($expiresInMinutes * 60));

        /** @var PasswordlessToken $token */
        $token = PasswordlessToken::create([
            'email' => $email,
            'token' => $rawToken,
            'type' => $type,
            'payload' => !empty($payload) ? $payload : null,
            'expires_at' => $expiresAt,
            'used_at' => null,
        ]);

        return $token;
    }

    /**
     * Build the full URL for the verification link.
     */
    public function buildVerifyUrl(string $token): string
    {
        $appUrl = rtrim((string) $this->config['app_url'], '/');
        $verifyRoute = '/' . ltrim((string) $this->config['verify_route'], '/');

        return "{$appUrl}{$verifyRoute}/{$token}";
    }

    /**
     * Verify a token without consuming it yet.
     *
     * @throws InvalidTokenException if token is invalid or already used
     * @throws TokenExpiredException if token is expired
     */
    public function verifyToken(string $token, ?string $expectedType = null): PasswordlessToken
    {
        /** @var PasswordlessToken|null $record */
        $record = PasswordlessToken::where('token', '=', $token)->first();

        if ($record === null) {
            throw new InvalidTokenException("The provided authentication token is invalid.");
        }

        if ($record->isUsed()) {
            throw new InvalidTokenException("This authentication link has already been used.");
        }

        if ($record->isExpired()) {
            throw new TokenExpiredException("This authentication link has expired. Please request a new one.");
        }

        if ($expectedType !== null && $record->type !== $expectedType) {
            throw new InvalidTokenException("Invalid token type for this operation.");
        }

        return $record;
    }

    /**
     * Consume a token, marking it as used.
     */
    public function consumeToken(string|PasswordlessToken $token): PasswordlessToken
    {
        $record = is_string($token) ? $this->verifyToken($token) : $token;
        $record->markUsed();
        return $record;
    }

    /**
     * Authenticate and log in the user using the magic token.
     *
     * @param string|PasswordlessToken $token
     * @param bool $remember
     * @param string $guard
     * @return AuthenticatableInterface
     */
    public function authenticate(
        string|PasswordlessToken $token,
        bool $remember = true,
        string $guard = 'web'
    ): AuthenticatableInterface {
        $record = is_string($token) ? $this->verifyToken($token) : $token;

        $userModel = $this->getUserModel();
        $user = null;

        if ($record->type === PasswordlessToken::TYPE_REGISTER) {
            // Create user from token payload if not exists
            if (class_exists($userModel) && method_exists($userModel, 'where')) {
                $user = $userModel::where('email', '=', $record->email)->first();
            }

            if ($user === null && class_exists($userModel) && method_exists($userModel, 'create')) {
                $payload = (array) ($record->payload ?? []);
                $payload['email'] = $record->email;
                if (!isset($payload['name'])) {
                    $payload['name'] = explode('@', $record->email)[0];
                }
                $user = $userModel::create($payload);
            }
        } else {
            // Login or Recovery: find existing user
            if (class_exists($userModel) && method_exists($userModel, 'where')) {
                $user = $userModel::where('email', '=', $record->email)->first();
            }

            if ($user === null && !empty($this->config['auto_register']) && class_exists($userModel)) {
                $user = $userModel::create([
                    'email' => $record->email,
                    'name' => explode('@', $record->email)[0],
                ]);
            }
        }

        if (!$user instanceof AuthenticatableInterface) {
            throw new UserNotFoundException("Could not resolve authenticatable user for '{$record->email}'.");
        }

        // Mark token as used
        $record->markUsed();

        // Perform login in AuthManager
        AuthManager::getInstance()->guard($guard)->login($user, $remember);

        return $user;
    }

    /**
     * Delete expired tokens from database.
     */
    public function cleanExpiredTokens(): int
    {
        try {
            $now = date('Y-m-d H:i:s');
            return PasswordlessToken::where('expires_at', '<=', $now)->delete();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Enforce rate limiting on token requests.
     *
     * @throws TooManyRequestsException
     */
    protected function enforceRateLimit(string $key): void
    {
        if (empty($this->config['rate_limit']['enabled'])) {
            return;
        }

        $maxAttempts = (int) ($this->config['rate_limit']['max_attempts'] ?? 5);
        $decaySeconds = (int) ($this->config['rate_limit']['decay_seconds'] ?? 3600);

        if ($this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $availableIn = $this->rateLimiter->availableIn($key);
            throw new TooManyRequestsException(
                message: "Too many authentication link requests. Please try again in {$availableIn} seconds.",
                availableInSeconds: $availableIn
            );
        }

        $this->rateLimiter->hit($key, $decaySeconds);
    }

    /**
     * Get the configured user model class.
     */
    public function getUserModel(): string
    {
        return (string) ($this->config['user_model'] ?? 'App\\Models\\User');
    }
}

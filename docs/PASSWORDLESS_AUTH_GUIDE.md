# Passwordless Authentication — Setup & Developer Guide

The **Passwordless Authentication Subsystem** in Switch Foundation (`switch/foundation`) provides an enterprise-grade, cryptographically secure magic-link authentication system.

Users sign in, register, and recover accounts using time-limited, single-use magic links delivered via email — **no passwords required**.

---

## 📑 Table of Contents

1. [Architecture & Design Philosophy](#1-architecture--design-philosophy)
2. [Quick Setup in 4 Steps](#2-quick-setup-in-4-steps)
3. [Building Custom UI Views](#3-building-custom-ui-views)
4. [Dual-Mode: HTML Web & JSON API](#4-dual-mode-html-web--json-api)
5. [Configuration Options (`config/auth.php`)](#5-configuration-options-configauthphp)
6. [Security & Rate Limiting](#6-security--rate-limiting)
7. [Programmatic Usage & API Reference](#7-programmatic-usage--api-reference)

---

## 1. Architecture & Design Philosophy

Switch Framework follows a **Decoupled View Architecture**:
- **Framework Core**: Handles cryptographic token generation, database persistence, email dispatching, expiration tracking, rate limiting, and session authentication.
- **Your Application**: Owns and designs the user interface (Switch View templates, Tailwind/Bootstrap CSS, React/Vue frontends, etc.).

```
┌────────────────────────────────────────────────────────┐
│                   User Enters Email                    │
└──────────────────────────┬─────────────────────────────┘
                           │ POST /auth/login
                           ▼
┌────────────────────────────────────────────────────────┐
│      PasswordlessController :: requestLogin()          │
│  - Validates email address                             │
│  - Checks RateLimiter (max 5 requests/hr per email)   │
│  - Generates 64-char crypto token (expires in 15m)     │
│  - Sends MagicLinkMailable via MailManager             │
│  - Sets Flash Toast & Redirects to /auth/link-sent     │
└──────────────────────────┬─────────────────────────────┘
                           │ User clicks link in email
                           ▼
┌────────────────────────────────────────────────────────┐
│        PasswordlessController :: verify()              │
│  - Validates token exists, not expired, not used       │
│  - Marks token as used (single-use enforcement)        │
│  - Authenticates user in SessionGuard (Auth::login)    │
│  - Redirects to Dashboard / Intended destination       │
└────────────────────────────────────────────────────────┘
```

---

## 2. Quick Setup in 4 Steps

### Step 1: Run Database Migrations

Ensure your database has the `users` and `passwordless_tokens` tables:

```bash
php switch migrate
```

The `passwordless_tokens` table schema:
```sql
CREATE TABLE passwordless_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    type VARCHAR(20) NOT NULL DEFAULT 'login',  -- 'login', 'register', 'recovery'
    payload TEXT NULL,                          -- JSON payload for pending registration data
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME,
    updated_at DATETIME
);
```

---

### Step 2: Add `HasPasswordlessAuth` to Your User Model

In `app/Models/User.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Switch\Database\ORM\Model;
use Switch\Foundation\Auth\Access\AuthorizableTrait;
use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\Passwordless\HasPasswordlessAuth;

class User extends Model implements AuthenticatableInterface
{
    use HasPasswordlessAuth;
    use AuthorizableTrait;

    protected string $table = 'users';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'email',
        'password',       // Nullable for passwordless users
        'remember_token',
    ];
}
```

---

### Step 3: Create Your Custom `AuthController`

Create `app/Controllers/AuthController.php` extending `PasswordlessController`. You only need to define which view templates to render:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Auth\Passwordless\PasswordlessController;

class AuthController extends PasswordlessController
{
    /**
     * Where to redirect after successful login or registration.
     */
    protected function redirectTo(): string
    {
        return '/dashboard';
    }

    /**
     * Where to redirect after logout.
     */
    protected function redirectAfterLogout(): string
    {
        return '/auth/login';
    }

    /**
     * Render the Sign-In Page.
     */
    public function showLoginForm(): string|ResponseInterface
    {
        return $this->view('auth.login', ['title' => 'Sign In — MyApp']);
    }

    /**
     * Render the Registration Page.
     */
    public function showRegisterForm(): string|ResponseInterface
    {
        return $this->view('auth.register', ['title' => 'Create Account — MyApp']);
    }

    /**
     * Render the Account Recovery Page.
     */
    public function showRecoveryForm(): string|ResponseInterface
    {
        return $this->view('auth.recover', ['title' => 'Account Recovery — MyApp']);
    }

    /**
     * Render the "Check Your Email" confirmation page.
     */
    public function showLinkSent(ServerRequestInterface $request): string|ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $type = $request->getQueryParams()['type'] ?? 'login';

        return $this->view('auth.link-sent', [
            'title' => 'Check Your Email — MyApp',
            'email' => $email,
            'type' => $type,
        ]);
    }
}
```

---

### Step 4: Register Authentication Routes

In `routes/web.php`:

```php
use App\Controllers\AuthController;
use Switch\Foundation\Auth\Passwordless\PasswordlessRoutes;

// Registers full suite of GET & POST routes under /auth prefix
PasswordlessRoutes::register(AuthController::class);
```

This automatically maps:

| Method | URI Path | Route Name | Action | Description |
|---|---|---|---|---|
| `GET` | `/auth/login` | `auth.login` | `showLoginForm` | Displays the login form |
| `POST` | `/auth/login` | `auth.login.request` | `requestLogin` | Sends login magic link |
| `GET` | `/auth/register` | `auth.register` | `showRegisterForm` | Displays registration form |
| `POST` | `/auth/register` | `auth.register.request` | `requestRegister` | Sends registration confirmation link |
| `GET` | `/auth/recover` | `auth.recover` | `showRecoveryForm` | Displays account recovery form |
| `POST` | `/auth/recover` | `auth.recover.request` | `requestRecovery` | Sends recovery link |
| `GET` | `/auth/verify/{token}` | `auth.verify` | `verify` | Validates token & logs in user |
| `GET` | `/auth/link-sent` | `auth.link_sent` | `showLinkSent` | Displays "Check your inbox" screen |
| `POST` | `/auth/logout` | `auth.logout` | `logout` | Logs user out of session |

---

## 3. Building Custom UI Views

Below are sample Switch View templates for your application's `resources/views/auth/` directory:

### 3.1 Login View (`resources/views/auth/login.switch.php`)
```html
<layout name="layouts.app" title="Sign In">
    <div class="auth-card">
        <h2>Sign in without a password</h2>
        <p>Enter your email and we'll send you an instant sign-in link.</p>

        <form action="/auth/login" method="POST" switch-to>
            @csrf
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="you@example.com" autofocus />
            </div>

            <button type="submit" class="btn btn-primary">Send Magic Sign-In Link</button>
        </form>

        <div class="auth-links">
            <a href="/auth/register" switch-to>Don't have an account? Sign Up</a>
            <a href="/auth/recover" switch-to>Forgot account details?</a>
        </div>
    </div>
</layout>
```

### 3.2 Registration View (`resources/views/auth/register.switch.php`)
```html
<layout name="layouts.app" title="Create Account">
    <div class="auth-card">
        <h2>Create an Account</h2>
        <p>No passwords required. Confirm via your email.</p>

        <form action="/auth/register" method="POST" switch-to>
            @csrf
            <div class="form-group">
                <label for="name">Your Name</label>
                <input type="text" id="name" name="name" required placeholder="Jane Doe" />
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="jane@example.com" />
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>

        <div class="auth-links">
            <a href="/auth/login" switch-to>Already have an account? Sign In</a>
        </div>
    </div>
</layout>
```

### 3.3 Link Sent Confirmation View (`resources/views/auth/link-sent.switch.php`)
```html
<layout name="layouts.app" title="Check Your Email">
    <div class="auth-card text-center">
        <div class="icon-mail">✉️</div>
        <h2>Magic Link Sent!</h2>
        <p>We've sent a secure verification link to <strong>{{ $email }}</strong>.</p>
        <p class="text-muted">Click the link in the email to complete authentication. The link expires in 15 minutes.</p>

        <div class="mt-4">
            <a href="/auth/login" class="btn btn-secondary" switch-to>Back to Sign In</a>
        </div>
    </div>
</layout>
```

---

## 4. Dual-Mode: HTML Web & JSON API

`PasswordlessController` automatically inspects incoming requests. It delivers rich HTML responses with Flash/Toast notifications for standard browsers, and clean JSON payloads for API or mobile clients:

### Submitting via JSON / Fetch API:
```javascript
// POST /auth/login
const response = await fetch('/auth/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    body: JSON.stringify({ email: 'user@example.com' })
});

const data = await response.json();
// Response: { "success": true, "message": "We sent a magic sign-in link...", "redirect": "/auth/link-sent..." }
```

### Verifying Token via JSON:
```javascript
// GET /auth/verify/1f9b8c...
const response = await fetch('/auth/verify/1f9b8c...', {
    headers: { 'Accept': 'application/json' }
});

const data = await response.json();
// Response:
// {
//   "success": true,
//   "message": "Authenticated successfully.",
//   "user": { "id": 1, "name": "Jane Doe", "email": "jane@example.com" },
//   "redirect": "/dashboard"
// }
```

---

## 5. Configuration Options (`config/auth.php`)

You can customize token lifetimes, entropy lengths, and rate limits in `config/auth.php`:

```php
return [
    'default' => 'web',

    'passwordless' => [
        // Number of minutes before sign-in & registration links expire (default: 15)
        'token_expiry' => 15,

        // Number of minutes before account recovery links expire (default: 60)
        'recovery_expiry' => 60,

        // Hex length of generated cryptographic tokens (default: 64)
        'token_length' => 64,

        // Route path for token verification
        'verify_route' => '/auth/verify',

        // Rate limiting configuration
        'rate_limit' => [
            'enabled' => true,
            'max_attempts' => 5,      // Max requests allowed
            'decay_seconds' => 3600,  // Window duration in seconds (1 hour)
        ],

        // Automatically create account if an unregistered user attempts to log in
        'auto_register' => false,
    ],
];
```

---

## 6. Security & Rate Limiting

1. **Cryptographic Entropy**: Generated via PHP's `random_bytes(32)` providing 256 bits of entropy.
2. **Single-Use Enforcement**: Tokens are immediately marked with `used_at = NOW()` upon authentication. Replay attacks are rejected.
3. **Time-Limited Expiry**: Expired tokens are rejected and can be pruned using `PasswordlessManager::cleanExpiredTokens()`.
4. **Brute-Force & Flood Protection**: Integrated with `Switch\Foundation\Api\RateLimiter`. If an email receives more than 5 requests in 1 hour, a `TooManyRequestsException` (HTTP 429) is thrown.
5. **No Password Hashes to Leak**: Because passwords are not required, user databases are immune to password hash cracking.

---

## 7. Programmatic Usage & API Reference

You can interact directly with the passwordless engine in services, CLI commands, or custom controllers:

### Using the `Auth` Facade:
```php
use Switch\Foundation\Auth\Facade\Auth;

// Send magic sign-in link
Auth::sendLoginLink('developer@example.com');

// Send registration link with custom metadata
Auth::sendRegistrationLink('newbie@example.com', [
    'name' => 'Alex Rivera',
    'plan' => 'pro'
]);

// Send recovery link
Auth::sendRecoveryLink('developer@example.com');
```

### Using the Global Helper:
```php
$manager = passwordless();

// Generate a token manually
$token = $manager->generateToken('user@example.com', type: 'login', expiresInMinutes: 30);
$url = $manager->buildVerifyUrl($token->token);

// Authenticate manually
$user = $manager->authenticate($token->token);

// Prune expired tokens
$deleted = $manager->cleanExpiredTokens();
```

### User Model Methods (via `HasPasswordlessAuth`):
```php
$user = User::findByEmail('alice@example.com');

if ($user) {
    $user->sendLoginLink();
    $user->sendRecoveryLink();
}
```

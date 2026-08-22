# Switch Foundation (`switch/foundation`)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/celionatti/switch-foundation)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net)

**Switch Foundation** is the Swiss Army knife powerhouse for the **Switch Framework** and modern PHP applications. It houses built-in, decoupled, zero-dependency implementations for:

- 🔐 **Auth & Security** (Guards, Sessions, Tokens, API Keys, Bcrypt & Argon2id Hashing, Gates & Policies)
- ⚡ **Cache Engine** (File, Array, Database, Redis multi-driver caching, tagged invalidation & `remember()`)
- 📂 **Storage & Filesystem** (Local & Public Disks, File Uploads, Mime Types, Streaming)
- 🖼️ **Image Processing** (Native GD Resizing, Cropping, Smart Fitting, WebP/AVIF conversions, Watermarks)
- 📬 **Mailer** (SMTP, Sendmail, Log, Array transports, Mailables with Switch View template rendering)
- ⏱️ **Queue System** (Sync, Database, Array drivers, Job Dispatchers, Retry handling & CLI Worker)
- 🚀 **API & Rate Limiting** (JsonResource, ResourceCollection, Standardized Responses, Token Bucket Throttling)

---

## 📦 Installation

```bash
composer require switch/foundation
```

---

## 🔐 1. Auth & Security

### Authentication
```php
use Switch\Foundation\Auth\Facade\Auth;

// Attempt login with session & optional remember-me
if (Auth::attempt(['email' => $email, 'password' => $password], remember: true)) {
    $user = Auth::user();
}

// Check status
if (Auth::check()) {
    $id = Auth::id();
}

// Logout
Auth::logout();
```

### Password Hashing
```php
use Switch\Foundation\Auth\Facade\Hash;

$hashed = Hash::make('secret123');
if (Hash::check('secret123', $hashed)) {
    // Verified
}
```

### Gates & Authorization
```php
use Switch\Foundation\Auth\Access\Gate;

Gate::define('edit-post', function ($user, $post) {
    return $user->id === $post->user_id;
});

if (Gate::allows('edit-post', $post)) {
    // User is authorized
}
```

---

## ⚡ 2. Cache Engine

```php
use Switch\Foundation\Cache\Facade\Cache;

// Put with TTL in seconds
Cache::put('key', 'value', 3600);

// Retrieve or compute
$users = Cache::remember('all_users', 600, function () {
    return User::all();
});

// Tagged Cache
Cache::tags(['posts', 'authors'])->flush();
```

---

## 📂 3. Storage & Filesystem

```php
use Switch\Foundation\Storage\Facade\Storage;

// Store files
Storage::disk('public')->put('avatars/1.png', $fileContents);

// Get public URL
$url = Storage::disk('public')->url('avatars/1.png');

// Check & delete
if (Storage::exists('temp/report.pdf')) {
    Storage::delete('temp/report.pdf');
}
```

---

## 🖼️ 4. Image Manipulation

```php
use Switch\Foundation\Image\Facade\Image;

Image::load('photo.jpg')
    ->fit(800, 600)
    ->brightness(10)
    ->watermark('logo.png', position: 'bottom-right')
    ->save('optimized.webp', quality: 85);
```

---

## 📬 5. Mailer

```php
use Switch\Foundation\Mailer\Facade\Mail;
use Switch\Foundation\Mailer\Mailable;

class WelcomeMail extends Mailable
{
    public function __construct(public $user)
    {
        $this->subject('Welcome to Switch Framework!')
             ->view('emails.welcome', ['user' => $user]);
    }
}

Mail::to('user@example.com')->send(new WelcomeMail($user));
```

---

## ⏱️ 6. Queues & Background Jobs

```php
use Switch\Foundation\Queue\Job;

class ProcessInvoice extends Job
{
    public int $tries = 3;

    public function __construct(public int $invoiceId) {}

    public function handle(): void
    {
        // Process invoice in background...
    }
}

// Dispatch job
ProcessInvoice::dispatch(42)->onQueue('invoices')->delay(60);
```

---

## 🚀 7. API Resources & Rate Limiting

```php
use Switch\Foundation\Api\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'posts' => $this->whenLoaded('posts'),
        ];
    }
}

return UserResource::make($user);
```

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Foundation package is open-source software licensed under the [MIT license](LICENSE).

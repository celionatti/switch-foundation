# Switch Foundation (`switch/foundation`)

[![Latest Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/celionatti/switch-foundation)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.2-777bb4.svg)](https://php.net)

**Switch Foundation** is the Swiss Army knife powerhouse for the **Switch Framework** and modern PHP applications. It houses built-in, decoupled, zero-dependency implementations for:

- 🔐 **Auth & Security** (Guards, Sessions, Tokens, API Keys, Bcrypt & Argon2id Hashing, Gates & Policies)
- ⚡ **Cache Engine** (File, Array, Database, Redis multi-driver caching, tagged invalidation & `remember()`)
- 📂 **Storage & Filesystem** (Local & Public Disks, File Uploads, Mime Types, Streaming)
- 🖼️ **Image Processing** (Native GD Resizing, Cropping, Smart Fitting, WebP/AVIF conversions, Watermarks)
- 📬 **Mailer & Queued Emails** (SMTP, Sendmail, Log, Array transports, Mailables with automatic background queueing)
- ⏱️ **Queue System** (Sync, Database, Array drivers, Job Dispatchers, Retry handling & CLI Worker)
- 🚀 **API & Rate Limiting** (JsonResource, ResourceCollection, Standardized Responses, Token Bucket Throttling)
- 📢 **Real-Time Notifications** (Multi-Channel: Database, Mail, Broadcast, SSE Server-Sent Events, JavaScript client bridge)

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

## 📬 5. Mailer & Automatic Queued Emails

```php
use Switch\Foundation\Mailer\Facade\Mail;
use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Queue\ShouldQueue;

// Normal or Queued Mailable
class WelcomeMail extends Mailable implements ShouldQueue
{
    public function __construct(public $user)
    {
        $this->subject('Welcome to Switch Framework!')
             ->view('emails.welcome', ['user' => $user]);
    }
}

// Automatically pushed to background queue without blocking HTTP response!
Mail::to('user@example.com')->send(new WelcomeMail($user));

// Or explicit queueing
Mail::to('user@example.com')->queue($mailable);
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

## 📢 8. Real-Time Multi-Channel Notifications & SSE Stream

### Creating a Notification
```php
use Switch\Foundation\Notification\Notification;
use Switch\Foundation\Notification\ShouldQueue;

class OrderShipped extends Notification implements ShouldQueue
{
    public function __construct(public string $orderNumber)
    {
        parent::__construct();
    }

    public function via(mixed $notifiable): array
    {
        return ['database', 'mail', 'broadcast', 'sse'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'order_number' => $this->orderNumber,
            'title' => 'Order Shipped',
            'message' => "Your order #{$this->orderNumber} is on the way!",
        ];
    }
}
```

### Sending to Notifiables
```php
// Via model
$user->notify(new OrderShipped('ORD-12345'));

// Unread notifications
$unread = $user->unreadNotifications();
$user->markAllNotificationsAsRead();

// On-demand routing without a user model
Notification::route('mail', 'ops@example.com')->notify(new ServerAlert());
```

### Real-Time JavaScript Client Bridge (SSE)
Insert zero-dependency real-time event listening anywhere in your view layout:
```html
<?= notification_stream('/api/notifications/stream') ?>
```
Listen to real-time notifications in JavaScript:
```javascript
window.addEventListener('switch:notification', (e) => {
    console.log('Real-time notification received:', e.detail);
});
```

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Foundation package is open-source software licensed under the [MIT license](LICENSE).

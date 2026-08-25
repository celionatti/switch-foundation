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

## 🌐 9. React-like Context API (Global & Scoped State Management)

Manage application state with React-inspired scoped context providers, state mutation, and subscribers:

### Providing & Consuming State
```php
use Switch\Foundation\Context\Facade\Context;

// 1. Global state
Context::provide('theme', ['mode' => 'dark', 'primary' => '#6366f1']);

// 2. Scoped Provider (automatically restores previous state upon callback exit)
$output = Context::provide('cart', $cartData, function ($cart) {
    return View::render('checkout');
});

// 3. Dot-notation consumption
$mode = Context::use('theme.mode'); // 'dark'
$mode = context('theme.mode');       // Using global helper
```

### State Mutations & Subscriptions
```php
// Mutate state with previous state callback
Context::mutate('counter', fn($prev) => ['count' => ($prev['count'] ?? 0) + 1]);

// Subscribe to state changes
$unsubscribe = Context::subscribe('cart', function ($newCart, $oldCart) {
    Log::info('Cart updated', ['items' => count($newCart['items'])]);
});
```

### View Template Syntax
```html
<!-- Provider -->
<context name="theme" :value="['mode' => 'dark']">
    <!-- Consumer inside any nested child component -->
    <use-context name="theme" as="$theme" />
    <p>Active mode: {{ $theme.mode }}</p>
</context>
```

### Switch Live JavaScript Client Bridge
```javascript
// Access or mutate context on the frontend reactively
SwitchLive.useContext('theme.mode'); // 'dark'
SwitchLive.setContext('theme.mode', 'light');

// Elements automatically bind and re-render
// <span data-bind="theme.mode"></span>
```

---

## 📊 10. Static Datasets & Mock Data Engine

Load static reference data (JSON, PHP, CSV) and generate realistic mock entities for rapid prototyping without a database:

### Loading Static Datasets
```php
use Switch\Foundation\Data\Facade\Data;

// Loads data/countries.json or data/countries.php
$countries = Data::get('countries');
$usState = Data::get('countries.US.name');

// Collection querying helpers
$proPlan = Data::find('pricing_plans', 'pro');
$activeUsers = Data::where('users', 'active', true);
$names = Data::pluck('plans', 'name');
```

### Generating Mock Records & Custom Blueprints
```php
// Generate realistic mock records instantly
$users = Data::mock('user', 5);
$products = Data::mock('product', 10, ['category' => 'Electronics']);

// Register custom entity blueprints
Data::define('author', function ($i, $faker) {
    return [
        'id' => $i,
        'name' => $faker->name(),
        'email' => $faker->email(),
        'avatar' => $faker->avatar(),
        'bio' => $faker->paragraph(),
    ];
});

$authors = mock('author', 3);
```

### View Template Integration
```html
<!-- Load static dataset -->
<data source="countries" as="$countries" />

<!-- Generate mock records directly in view -->
<data mock="product" count="6" as="$products" />

<foreach items="$products" as="$product">
    <x-card title="$product.title">
        <p>${{ $product.price }}</p>
    </x-card>
</foreach>
```

---

## ⚡ 11. Advanced Collection & Lazy Stream Engine

An expressive, modern collection engine with 80+ fluent methods, higher-order messaging, hierarchical tree generation, statistical aggregations, and generator-backed lazy streaming:

### Creating Collections
```php
use Switch\Foundation\Collection\Collection;
use Switch\Foundation\Collection\LazyCollection;

// Via global helper
$collection = collect([1, 2, 3, 4, 5]);

// Generators
$times = Collection::times(5, fn($i) => ['id' => $i]);
$range = Collection::range(1, 100);
```

### Fluent Filtering, Querying & Sorting
```php
$users = collect([
    ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'points' => 450],
    ['id' => 2, 'name' => 'Bob', 'role' => 'member', 'points' => 120],
    ['id' => 3, 'name' => 'Charlie', 'role' => 'member', 'points' => 300],
]);

// Where operations
$admins = $users->where('role', 'admin');
$highPoints = $users->where('points', '>=', 300);
$staff = $users->whereIn('role', ['admin', 'editor']);
$cNames = $users->whereLike('name', 'C*');

// Sorting & Deep Pluck
$topUsers = $users->sortByDesc('points')->pluck('name'); // ['Alice', 'Charlie', 'Bob']
```

### Transformations, Partitioning & Math
```php
// Partition into two collections (e.g. adults and minors)
[$adults, $minors] = $ages->partition(fn($age) => $age >= 18);

// Deep grouping & keying
$byRole = $users->groupBy('role');

// Math & Statistical Aggregations
$totalPoints = $users->sum('points');
$avgPoints = $users->avg('points');
$medianPoints = $users->median('points');
$pct = $users->percentage(fn($u) => $u['points'] > 200); // 66.67%
```

### Hierarchical Tree Generation (`toTree()` & `flattenTree()`)
Instantly transform a flat list of parent-child records into a nested hierarchical tree structure in \(O(N)\) time:
```php
$categories = collect([
    ['id' => 1, 'name' => 'Electronics', 'parent_id' => null],
    ['id' => 2, 'name' => 'Laptops', 'parent_id' => 1],
    ['id' => 3, 'name' => 'Gaming Laptops', 'parent_id' => 2],
    ['id' => 4, 'name' => 'Books', 'parent_id' => null],
]);

// Build nested tree structure
$tree = $categories->toTree();
// Output: Electronics (with Laptops -> Gaming Laptops nested in children) and Books

// Flatten tree back to flat collection
$flat = $tree->flattenTree();
```

### Higher-Order Messaging
```php
// Call method or access property on all items
$names = $userModels->map->name;
$activeUsers = $userModels->filter->isActive();
$userModels->each->delete();
```

### Generator-Backed Lazy Stream Collections (`LazyCollection`)
Process massive datasets (millions of rows, huge CSVs) with constant \(O(1)\) memory usage:
```php
$stream = LazyCollection::times(1_000_000)
    ->filter(fn($i) => $i % 2 === 0)
    ->map(fn($i) => $i * 10)
    ->take(10);

// Converted to eager collection when needed
$eager = $stream->eager();
```

---

## ⚡ 11. Switch Actions (Single-Action Architecture)

Switch Actions unite your business logic, validation, authorization, HTTP controller handling, and background job queueing into a single, cohesive, highly testable class:

```php
namespace App\Actions;

use Switch\Foundation\Action\Action;
use App\Models\User;

class CreateUserAction extends Action
{
    public function authorize(): bool
    {
        return true; // or Gate::allows('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required',
            'email' => 'required|email',
            'role' => 'nullable',
        ];
    }

    public function handle(array $data): User
    {
        return User::create($data);
    }
}
```

### 4 Execution Modes in 1 Class:
```php
// 1. Direct Service Call
$user = CreateUserAction::run(['name' => 'John', 'email' => 'john@test.com']);

// 2. Direct HTTP Controller in Route
Route::post('/users', CreateUserAction::class);

// 3. Queued Background Job
CreateUserAction::dispatch(['name' => 'Queued John', 'email' => 'john@test.com']);

// 4. CLI / Artisan Command Handler
$user = (new CreateUserAction())->handle($cliInput);
```

---

## 🎯 12. Instant Auto-CRUD & Query Filter API Engine

Instantly expose a production-ready REST API with deep filtering, searching, multi-field sorting, pagination, and eager loading for any ORM Model in one line:

```php
use Switch\Router\Facade\Route;
use App\Models\Product;

// Exposes 5 RESTful API routes: GET /, GET /{id}, POST /, PUT /{id}, DELETE /{id}
Route::apiResource('api/v1/products', Product::class, [
    'rules' => [
        'title' => 'required',
        'price' => 'required|numeric',
    ],
    'searchable' => ['title', 'description'],
    'per_page' => 15,
]);
```

### Powerful Standardized Query Filter API:
Clients can use advanced filtering without writing a single line of backend query code:
- **Filters**: `GET /api/v1/products?filter[category]=laptops&filter[price][gte]=1000`
- **Operators**: `eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`, `null`, `not_null`
- **Full-Text Search**: `GET /api/v1/products?search=macbook`
- **Sorting**: `GET /api/v1/products?sort=-price,title` (descending price, ascending title)
- **Field Selection (Sparse Fieldsets)**: `GET /api/v1/products?fields=id,title,price`
- **Relationship Eager-Loading**: `GET /api/v1/products?include=category,reviews,tags`
- **Pagination**: `GET /api/v1/products?page=2&per_page=25`

---

## 🏷️ 13. Declarative Behavioral Attributes

Decorate your action handlers and controller methods with native PHP 8.2+ declarative attributes:

```php
use Switch\Router\Attributes\Get;
use Switch\Router\Attributes\Post;
use Switch\Foundation\Attributes\Authorize;
use Switch\Foundation\Attributes\RateLimit;
use Switch\Foundation\Attributes\Cached;

class OrderController
{
    #[Get('/orders', name: 'orders.index')]
    #[Cached(ttl: 300, tags: ['orders'])]
    public function index() { ... }

    #[Post('/orders')]
    #[Authorize('create_orders')]
    #[RateLimit('30/minute')]
    public function store() { ... }
}
```

---

## 🧪 Testing

```bash
composer test
```

---

## 📄 License

The Switch Foundation package is open-source software licensed under the [MIT license](LICENSE).



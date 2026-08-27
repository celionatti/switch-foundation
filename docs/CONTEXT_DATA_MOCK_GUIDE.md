# Switch Framework — Context API, Data & Mocking Complete Manual

This comprehensive guide covers the architecture, real-world patterns, and API reference for the **Context API**, **Static Data Manager**, and **Mock Blueprint Engine** in `switch/foundation`.

---

## 📑 Table of Contents

1. [The Context API: Core Concepts & Importance](#1-the-context-api-core-concepts--importance)
2. [Server Context vs Client Context (`share` & `markClient`)](#2-server-context-vs-client-context-share--markclient)
3. [Real-World Server & Database Use Cases](#3-real-world-server--database-use-cases)
   - [3.1 Multi-Tenant Database Isolation](#31-multi-tenant-database-isolation)
   - [3.2 Automatic Audit Trail & User Attribution](#32-automatic-audit-trail--user-attribution)
   - [3.3 Distributed Request Tracing & Correlation IDs](#33-distributed-request-tracing--correlation-ids)
   - [3.4 Scoped Execution Boundaries (Provider Pattern)](#34-scoped-execution-boundaries-provider-pattern)
   - [3.5 View Template Integration](#35-view-template-integration)
4. [Static Data Subsystem (`data()`)](#4-static-data-subsystem-data)
5. [Mock & Blueprint Generator (`mock()` & `fake()`)](#5-mock--blueprint-generator-mock--fake)
6. [Complete API Reference & Cheat Sheet](#6-complete-api-reference--cheat-sheet)

---

## 1. The Context API: Core Concepts & Importance

### What is Context?
In complex web applications, passing global or ambient state (such as the currently authenticated user, active tenant, user locale, currency, request trace ID, or theme) through dozens of controller methods, service constructors, repositories, and database queries is known as **"Prop Drilling"**. It pollutes method signatures and couples your code tightly.

The **Switch Context API** solves this by providing a high-performance, scoped, multi-layered state container that is accessible anywhere across your application lifecycle without static pollution or thread leaks.

### Key Highlights
- **Scoped Stack Memory**: Push and pop context states dynamically. When entering a callback scope, state changes are active; when the callback exits, previous state is automatically restored.
- **Dot-Notation Querying**: Deep access with defaults, e.g. `Context::use('tenant.database.host', '127.0.0.1')`.
- **Zero Thread Leaks**: Perfectly isolated per request in PHP-FPM, RoadRunner, Swoole, or FrankenPHP.
- **Client Synchronization**: Explicit opt-in sharing to synchronize selected server state directly with the frontend Switch Live client.

---

## 2. Server Context vs Client Context (`share` & `markClient`)

### Why are Server Contexts Private by Default?
Security is paramount. Server-side contexts often store sensitive details such as:
- Multi-tenant database credentials
- Internal system paths
- Secret tokens or authorization scopes
- Server memory metrics

By default, everything provided via `Context::provide()` is **strictly server-only**. It will never be leaked to the client JSON payload.

### When & How to Share with the Frontend Client

When you *do* want state to be available to your JavaScript SPA / Switch Live frontend (such as active theme, user profile, permitted UI permissions, or feature flags):

```php
use Switch\Foundation\Context\Facade\Context;

// 1. Method A: Context::share() (Sets value AND marks for client sync in one step)
Context::share('client.user', [
    'name' => 'Sarah Connor',
    'role' => 'Lead Architect',
    'locale' => 'en_US',
]);

// 2. Method B: Context::markClient() (Marks an existing server context for client sync)
Context::provide('app.theme', 'dark');
Context::markClient('app.theme');

// 3. Method C: Global helper
context_share('feature_flags', ['beta_dashboard' => true]);
```

### Retrieving the Client Payload
```php
$payload = Context::getClientPayload();
// Output:
// [
//     'client.user' => ['name' => 'Sarah Connor', ...],
//     'app.theme' => 'dark',
//     'feature_flags' => ['beta_dashboard' => true]
// ]
```

---

## 3. Real-World Server & Database Use Cases

### 3.1 Multi-Tenant Database Isolation

In SaaS applications, every tenant should only see their own records. Instead of manually passing `$tenantId` into every database query, you set the context in a Middleware and consume it in your Models or Global Scopes.

#### Step 1: Set Tenant Context in Middleware
```php
<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Foundation\Context\Facade\Context;

class IdentifyTenantMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Resolve tenant from header, subdomain, or authenticated session
        $tenantId = $request->getHeaderLine('X-Tenant-ID') ?: 'tenant_default';

        // Provide server-only tenant context
        Context::provide('tenant.id', $tenantId);
        Context::provide('tenant.config', [
            'database' => "tenant_{$tenantId}_db",
            'currency' => 'USD',
        ]);

        return $handler->handle($request);
    }
}
```

#### Step 2: Automatically Scope Database Queries
```php
<?php

namespace App\Models;

use Switch\Database\ORM\Model;
use Switch\Foundation\Context\Facade\Context;

class Invoice extends Model
{
    protected static string $table = 'invoices';

    /**
     * Query invoices strictly for the current active tenant.
     */
    public static function forCurrentTenant()
    {
        $tenantId = Context::use('tenant.id', 'tenant_default');
        return static::query()->where('tenant_id', '=', $tenantId);
    }
}

// In any Controller or Action:
$invoices = Invoice::forCurrentTenant()->get();
```

---

### 3.2 Automatic Audit Trail & User Attribution

When records are created, updated, or deleted, models can automatically capture the current user ID and IP address without passing the `$request` or `$user` object into the model.

```php
<?php

namespace App\Models;

use Switch\Database\ORM\Model;
use Switch\Foundation\Context\Facade\Context;

class Post extends Model
{
    public static function createWithAudit(array $attributes): self
    {
        $attributes['created_by'] = Context::use('auth.user.id', 1);
        $attributes['client_ip'] = Context::use('request.ip', '127.0.0.1');

        $post = static::create($attributes);
        
        // Record audit trail
        $post->recordAudit('post_created', [
            'author' => Context::use('auth.user.email', 'system'),
            'trace_id' => Context::use('trace.id'),
        ]);

        return $post;
    }
}
```

---

### 3.3 Distributed Request Tracing & Correlation IDs

Tag all logs, database queries, and third-party API calls with a unified trace ID.

```php
// In Bootstrap or Global Middleware:
Context::provide('trace.id', bin2hex(random_bytes(16)));

// In Logger or Database Query Listener:
function logMessage(string $message): void {
    $traceId = Context::use('trace.id', 'system');
    file_put_contents('app.log', "[{$traceId}] {$message}\n", FILE_APPEND);
}
```

---

### 3.4 Scoped Execution Boundaries (Provider Pattern)

Execute code inside an isolated, temporary context boundary. When the closure returns, the previous state is restored automatically.

```php
use Switch\Foundation\Context\Facade\Context;

Context::provide('currency', 'USD');

echo Context::use('currency'); // "USD"

// Run code within a temporary EUR scope:
Context::provide('currency', 'EUR', function () {
    echo Context::use('currency'); // "EUR"
    
    // Any database calculations inside here use EUR
    calculateTaxes();
});

echo Context::use('currency'); // Automatically restored to "USD"!
```

---

### 3.5 View Template Integration

You can access or output context directly in your Switch templates:

```blade
{{-- 1. Using helper syntax --}}
<p>Current Theme: {{ context('app.theme', 'light') }}</p>
<p>Tenant: {{ context('tenant.config.database') }}</p>

{{-- 2. Using View Directives --}}
<context name="app.theme" default="dark" />
```

---

## 4. Static Data Subsystem (`data()`)

The **Data Subsystem** provides a zero-latency repository for loading and querying static configuration sets, lookup lists, country tables, currency registries, or JSON files.

### 4.1 Loading & Accessing Datasets
Store your data arrays in `config/data/` or provide them on the fly:

```php
use Switch\Foundation\Data\Facade\Data;

// 1. Retrieve dataset
$countries = Data::get('countries');

// 2. Query with dot notation
$usCurrency = Data::get('countries.US.currency', 'USD');

// 3. Global Helper
$roles = data('roles.admin.permissions');
```

---

## 5. Mock & Blueprint Generator (`mock()` & `fake()`)

The **Mock Subsystem** allows you to register schema blueprints and rapidly generate consistent mock data for prototypes, frontend testing, automated tests, and database seeders.

### 5.1 Built-in Generators
```php
use Switch\Foundation\Data\Facade\Data;

// Instant atomic fakes
$uuid = Data::fake('uuid');       // e.g. "a1b2c3d4-..."
$email = Data::fake('email');     // e.g. "user984@example.com"
$name = Data::fake('name');       // e.g. "Sarah Connor"
$price = Data::fake('price', 10, 500); // Float e.g. 149.99
$date = Data::fake('date');       // e.g. "2026-08-27"
$bool = Data::fake('boolean');    // true / false
```

### 5.2 Generating Mock Records
```php
// Generate 3 user objects
$users = Data::mock('user', 3);

// Generate 5 products with custom overrides
$products = Data::mock('product', 5, [
    'status' => 'in_stock',
    'category' => 'Hardware',
]);

// Using Global Helper
$mockPosts = mock('post', 10);
```

### 5.3 Defining Custom Blueprints
```php
use Switch\Foundation\Data\Facade\Data;

Data::blueprint('invoice', function (array $overrides = []) {
    return array_merge([
        'id' => 'INV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6)),
        'customer' => Data::fake('name'),
        'email' => Data::fake('email'),
        'total' => Data::fake('price', 50, 2000),
        'status' => 'pending',
        'created_at' => Data::fake('date'),
    ], $overrides);
});

// Use your custom blueprint anywhere:
$invoices = Data::mock('invoice', 5);
```

---

## 6. Complete API Reference & Cheat Sheet

| Category | Method / Syntax | Description |
| :--- | :--- | :--- |
| **Context** | `Context::provide(string $name, mixed $value, ?callable $cb = null)` | Provide a server-side context value or execute in a scoped boundary. |
| **Context** | `Context::share(string $name, mixed $value, ?callable $cb = null)` | Provide context AND synchronize it with client frontend JSON payload. |
| **Context** | `Context::markClient(string $name, bool $sync = true)` | Mark an existing context to be included in frontend client payload. |
| **Context** | `Context::use(string $name, mixed $default = null)` | Consume context value using dot-notation keys. |
| **Context** | `Context::getClientPayload(): array` | Returns all client-marked contexts formatted as an array. |
| **Context** | `Context::subscribe(string $name, callable $listener)` | Subscribe to mutations on a specific context. |
| **Context** | `context(string\|array\|null $name, mixed $default = null)` | Global helper for getting, setting, or batch providing contexts. |
| **Context** | `context_share(string\|array $name, mixed $value = null)` | Global helper to provide and mark context for client sync. |
| **Data** | `Data::get(string $key, mixed $default = null)` | Query static datasets with dot notation. |
| **Data** | `data(string $key, mixed $default = null)` | Global helper for static datasets. |
| **Mock** | `Data::mock(string $blueprint, int $count = 1, array $overrides = [])` | Generate array of mock records from a blueprint. |
| **Mock** | `Data::fake(string $type, ...$args)` | Generate atomic fake values (uuid, email, name, price, date). |
| **Mock** | `Data::blueprint(string $name, callable $generator)` | Register a custom mock blueprint generator. |
| **Mock** | `mock(string $blueprint, int $count = 1, array $overrides = [])` | Global helper to generate mock records. |

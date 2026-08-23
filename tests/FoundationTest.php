<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Api\ApiResponse;
use Switch\Foundation\Api\JsonResource;
use Switch\Foundation\Api\Middleware\ThrottleRequests;
use Switch\Foundation\Api\RateLimiter;
use Switch\Foundation\Api\ResourceCollection;
use Switch\Foundation\Auth\Access\AuthorizableTrait;
use Switch\Foundation\Auth\Access\Gate;
use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Facade\Auth;
use Switch\Foundation\Auth\Facade\Hash;
use Switch\Foundation\Auth\Guard\ApiKeyGuard;
use Switch\Foundation\Auth\Guard\SessionGuard;
use Switch\Foundation\Auth\Guard\TokenGuard;
use Switch\Foundation\Auth\Hash\HashManager;
use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Cache\Facade\Cache;
use Switch\Foundation\Cache\Store\ArrayStore;
use Switch\Foundation\Cache\Store\FileStore;
use Switch\Foundation\Image\Facade\Image;
use Switch\Foundation\Image\Image as ImageInstance;
use Switch\Foundation\Mailer\Facade\Mail;
use Switch\Foundation\Mailer\Mailable;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Mailer\Transport\ArrayTransport;
use Switch\Foundation\Queue\Driver\ArrayDriver;
use Switch\Foundation\Queue\Facade\Queue;
use Switch\Foundation\Queue\Job;
use Switch\Foundation\Queue\QueueManager;
use Switch\Foundation\Queue\Worker;
use Switch\Foundation\Storage\Facade\Storage;
use Switch\Foundation\Storage\LocalFilesystem;
use Switch\Foundation\Storage\StorageManager;
use Switch\Http\Response;
use Switch\Http\ServerRequest;
use Switch\Http\Uri;

// Mock User Model for Auth Testing
class TestUser implements AuthenticatableInterface
{
    use AuthorizableTrait;

    public int $id;
    public string $email;
    public string $password;
    public ?string $remember_token = null;
    public ?string $api_token = null;

    public function __construct(int $id, string $email, string $password, ?string $apiToken = null)
    {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->api_token = $apiToken;
    }

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return $this->id; }
    public function getAuthPassword(): string { return $this->password; }
    public function getRememberToken(): ?string { return $this->remember_token; }
    public function setRememberToken(?string $value): void { $this->remember_token = $value; }
    public function getRememberTokenName(): string { return 'remember_token'; }
}

// Mock Test Job
class TestJob extends Job
{
    public static bool $executed = false;
    public string $payload;

    public function __construct(string $payload = 'data')
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        self::$executed = true;
    }
}

class FoundationTest extends TestCase
{
    protected function setUp(): void
    {
        AuthManager::setInstance(null);
        CacheManager::setInstance(null);
        StorageManager::setInstance(null);
        MailManager::setInstance(null);
        QueueManager::setInstance(null);
        RateLimiter::setInstance(null);
    }

    // =========================================================================
    // 1. Auth & Password Hashing Tests
    // =========================================================================

    public function testPasswordHashingBcryptAndArgon2(): void
    {
        $hashed = Hash::make('password123');
        $this->assertTrue(Hash::check('password123', $hashed));
        $this->assertFalse(Hash::check('wrongpass', $hashed));
        $this->assertFalse(Hash::needsRehash($hashed));

        // Direct Argon2
        $argonHasher = HashManager::getInstance()->driver('argon');
        $argonHashed = $argonHasher->make('password123');
        $this->assertTrue($argonHasher->check('password123', $argonHashed));
    }

    public function testSessionGuardLoginAndAttempt(): void
    {
        $testUser = new TestUser(1, 'celio@example.com', Hash::make('secret'));

        $provider = function ($credentials) use ($testUser) {
            if (is_numeric($credentials) && (int) $credentials === 1) {
                return $testUser;
            }
            if (is_array($credentials) && ($credentials['email'] ?? null) === 'celio@example.com') {
                return $testUser;
            }
            return null;
        };

        $guard = new SessionGuard('web', $provider);
        $this->assertTrue($guard->guest());
        $this->assertFalse($guard->check());

        // Attempt login
        $success = $guard->attempt(['email' => 'celio@example.com', 'password' => 'secret']);
        $this->assertTrue($success);
        $this->assertTrue($guard->check());
        $this->assertEquals(1, $guard->id());
        $this->assertSame($testUser, $guard->user());

        // Logout
        $guard->logout();
        $this->assertTrue($guard->guest());
    }

    public function testTokenGuardAndApiKeyGuard(): void
    {
        $testUser = new TestUser(42, 'api@example.com', 'hash', 'secret_token_123');
        $provider = fn($token) => $token === 'secret_token_123' ? $testUser : null;

        // TokenGuard Bearer Header
        $tokenGuard = new TokenGuard($provider, 'api_token');
        $request = (new ServerRequest('GET', new Uri('https://example.com/api/user')))
            ->withHeader('Authorization', 'Bearer secret_token_123');
        $tokenGuard->setRequest($request);

        $this->assertTrue($tokenGuard->check());
        $this->assertEquals(42, $tokenGuard->id());

        // ApiKeyGuard
        $apiKeyGuard = new ApiKeyGuard($provider, 'X-API-Key');
        $apiKeyRequest = (new ServerRequest('GET', new Uri('https://example.com/api/data')))
            ->withHeader('X-API-Key', 'secret_token_123');
        $apiKeyGuard->setRequest($apiKeyRequest);

        $this->assertTrue($apiKeyGuard->check());
    }

    public function testGateAuthorizationAndPolicies(): void
    {
        $user = new TestUser(5, 'author@example.com', 'hash');
        $otherUser = new TestUser(10, 'other@example.com', 'hash');

        Auth::setUser($user);

        Gate::define('edit-settings', function ($auth) {
            return $auth !== null && $auth->getAuthIdentifier() === 5;
        });

        $this->assertTrue(Gate::allows('edit-settings'));
        $this->assertFalse(Gate::denies('edit-settings'));

        $this->assertTrue($user->can('edit-settings'));
        $this->assertTrue($otherUser->cannot('edit-settings'));
    }

    // =========================================================================
    // 2. Cache Subsystem Tests
    // =========================================================================

    public function testArrayCacheStoreAndRemember(): void
    {
        $store = new ArrayStore();
        $store->put('site_name', 'Switch App', 60);

        $this->assertTrue($store->has('site_name'));
        $this->assertEquals('Switch App', $store->get('site_name'));

        // Remember
        $computed = $store->remember('cached_calc', 60, fn() => 40 + 2);
        $this->assertEquals(42, $computed);
        $this->assertEquals(42, $store->get('cached_calc'));

        // Increment / Decrement
        $this->assertEquals(43, $store->increment('cached_calc', 1));
        $this->assertEquals(40, $store->decrement('cached_calc', 3));

        $store->forget('site_name');
        $this->assertFalse($store->has('site_name'));
    }

    public function testFileCacheStoreAndTagging(): void
    {
        $tempDir = sys_get_temp_dir() . '/switch_test_cache_' . uniqid();
        $fileStore = new FileStore($tempDir);

        $fileStore->put('config.item', ['theme' => 'dark'], 120);
        $this->assertTrue($fileStore->has('config.item'));
        $this->assertEquals(['theme' => 'dark'], $fileStore->get('config.item'));

        // Tagged cache
        $cache = new CacheManager([
            'default' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ]);
        CacheManager::setInstance($cache);

        $tagged = Cache::tags(['users', 'reports']);
        $tagged->put('report_1', 'Report Data', 3600);
        $this->assertEquals('Report Data', $tagged->get('report_1'));

        $tagged->flush();
        $this->assertNull($tagged->get('report_1'));

        $fileStore->flush();
    }

    // =========================================================================
    // 3. Storage Subsystem Tests
    // =========================================================================

    public function testLocalFilesystemOperations(): void
    {
        $tempDir = sys_get_temp_dir() . '/switch_test_storage_' . uniqid();
        $fs = new LocalFilesystem($tempDir, 'https://cdn.example.com/storage');

        $this->assertTrue($fs->put('documents/test.txt', 'Hello Switch Storage'));
        $this->assertTrue($fs->exists('documents/test.txt'));
        $this->assertEquals('Hello Switch Storage', $fs->get('documents/test.txt'));
        $this->assertEquals(20, $fs->size('documents/test.txt'));
        $this->assertEquals('text/plain', $fs->mimeType('documents/test.txt'));

        // Copy and Move
        $this->assertTrue($fs->copy('documents/test.txt', 'documents/copy.txt'));
        $this->assertTrue($fs->exists('documents/copy.txt'));
        $this->assertTrue($fs->move('documents/copy.txt', 'documents/moved.txt'));
        $this->assertTrue($fs->exists('documents/moved.txt'));

        // URL
        $this->assertEquals('https://cdn.example.com/storage/documents/test.txt', $fs->url('documents/test.txt'));

        // Delete
        $this->assertTrue($fs->delete(['documents/test.txt', 'documents/moved.txt']));
        $this->assertFalse($fs->exists('documents/test.txt'));

        $fs->deleteDirectory('');
    }

    // =========================================================================
    // 4. Image Processing Tests
    // =========================================================================

    public function testImageCreateResizeAndManipulate(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded in this PHP environment.');
        }

        $img = Image::create(400, 300, '#6366f1');
        $this->assertEquals(400, $img->getWidth());
        $this->assertEquals(300, $img->getHeight());

        // Resize
        $img->resize(200, 150, false);
        $this->assertEquals(200, $img->getWidth());
        $this->assertEquals(150, $img->getHeight());

        // Fit & Grayscale
        $img->fit(100, 100);
        $img->grayscale();
        $this->assertEquals(100, $img->getWidth());
        $this->assertEquals(100, $img->getHeight());

        // Encode to WebP string
        $encoded = $img->encode('png');
        $this->assertNotEmpty($encoded);
    }

    public function testImageLoadFromUploadedFileAndRequest(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not loaded.');
        }

        // Create temporary test image binary
        $sourceImg = Image::create(100, 100, '#ff0000');
        $binary = $sourceImg->encode('png');

        $stream = \Switch\Http\Stream::create($binary);
        $uploadedFile = new \Switch\Http\UploadedFile($stream, strlen($binary), UPLOAD_ERR_OK, 'avatar.png', 'image/png');

        $request = (new ServerRequest('POST', new Uri('https://example.com/upload')))
            ->withUploadedFiles(['avatar' => $uploadedFile]);

        $this->assertTrue($request->hasFile('avatar'));
        $file = $request->file('avatar');
        $this->assertNotNull($file);
        $this->assertTrue($file->isValid());
        $this->assertEquals('png', $file->extension());

        // Test Image::load() with UploadedFile directly
        $loadedImg = Image::load($file);
        $this->assertEquals(100, $loadedImg->getWidth());
        $this->assertEquals(100, $loadedImg->getHeight());

        // Test fluent $file->image() helper
        $fluentImg = $file->image()->fit(50, 50);
        $this->assertEquals(50, $fluentImg->getWidth());
        $this->assertEquals(50, $fluentImg->getHeight());
    }

    public function testStoragePutFileWithUploadedFile(): void
    {
        $tempDir = sys_get_temp_dir() . '/switch_test_storage_' . uniqid();
        $fs = new LocalFilesystem($tempDir, 'https://cdn.example.com/storage');

        $stream = \Switch\Http\Stream::create('Test file contents');
        $uploadedFile = new \Switch\Http\UploadedFile($stream, 18, UPLOAD_ERR_OK, 'document.txt', 'text/plain');

        $storedPath = $fs->putFile('uploads', $uploadedFile);
        $this->assertNotEquals(false, $storedPath);
        $this->assertTrue($fs->exists($storedPath));
        $this->assertEquals('Test file contents', $fs->get($storedPath));

        $stream2 = \Switch\Http\Stream::create('Custom file contents');
        $uploadedFile2 = new \Switch\Http\UploadedFile($stream2, 20, UPLOAD_ERR_OK, 'custom.txt', 'text/plain');

        $storedAs = $fs->putFileAs('uploads', $uploadedFile2, 'custom.txt');
        $this->assertEquals('uploads/custom.txt', $storedAs);
        $this->assertTrue($fs->exists('uploads/custom.txt'));

        $fs->deleteDirectory('');
    }

    // =========================================================================
    // 5. Mailer Subsystem Tests
    // =========================================================================

    public function testMailerWithArrayTransport(): void
    {
        $arrayTransport = new ArrayTransport();
        $mailManager = new MailManager([
            'default' => 'array',
            'mailers' => ['array' => ['transport' => 'array']],
            'from' => ['address' => 'noreply@switch.test', 'name' => 'Switch Test'],
        ]);
        MailManager::setInstance($mailManager);

        $mailable = (new Mailable())
            ->to('recipient@example.com', 'John Doe')
            ->subject('Your Order Confirmation')
            ->html('<h1>Thank you for your order!</h1>')
            ->text('Thank you for your order!');

        $this->assertTrue(Mail::send($mailable));
        $this->assertEquals('Your Order Confirmation', $mailable->getSubject());
        $this->assertStringContainsString('Subject: =?UTF-8?B?', $mailable->renderRaw());
        $this->assertStringContainsString('recipient@example.com', $mailable->renderRaw());
    }

    // =========================================================================
    // 6. Queue Subsystem Tests
    // =========================================================================

    public function testArrayQueueDriverAndWorker(): void
    {
        TestJob::$executed = false;

        $arrayDriver = new ArrayDriver();
        $queueManager = new QueueManager([
            'default' => 'array',
            'connections' => ['array' => ['driver' => 'array']],
        ]);
        QueueManager::setInstance($queueManager);

        $job = new TestJob('sample_payload');
        $jobId = Queue::push($job);
        $this->assertEquals(1, $jobId);
        $this->assertEquals(1, Queue::size());

        $worker = new Worker($queueManager);
        $processed = $worker->processNextJob('default');

        $this->assertTrue($processed);
        $this->assertTrue(TestJob::$executed);
        $this->assertEquals(0, Queue::size());
    }

    // =========================================================================
    // 7. API Resources & Rate Limiting Tests
    // =========================================================================

    public function testJsonResourceAndApiResponse(): void
    {
        $data = ['id' => 10, 'name' => 'Switch Developer', 'role' => 'admin'];
        $resource = new class($data) extends JsonResource {
            public function toArray(): array
            {
                return [
                    'id' => $this->id,
                    'name' => strtoupper($this->name),
                    'is_admin' => $this->when($this->role === 'admin', true),
                    'secret' => $this->when(false, 'hidden'),
                ];
            }
        };

        $resolved = $resource->resolve();
        $this->assertEquals(10, $resolved['id']);
        $this->assertEquals('SWITCH DEVELOPER', $resolved['name']);
        $this->assertTrue($resolved['is_admin']);
        $this->assertArrayNotHasKey('secret', $resolved);

        // ApiResponse
        $res = ApiResponse::success($resolved, 'User retrieved');
        $this->assertEquals(200, $res->getStatusCode());
        $this->assertStringContainsString('SWITCH DEVELOPER', (string) $res->getBody());
    }

    public function testRateLimiterAndThrottleMiddleware(): void
    {
        $cache = new CacheManager(['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]);
        $limiter = new RateLimiter($cache);

        $key = 'client_test_key';
        $this->assertFalse($limiter->tooManyAttempts($key, 3));

        $limiter->hit($key, 60);
        $limiter->hit($key, 60);
        $limiter->hit($key, 60);

        $this->assertTrue($limiter->tooManyAttempts($key, 3));
        $this->assertEquals(0, $limiter->remaining($key, 3));

        $limiter->resetAttempts($key);
        $this->assertFalse($limiter->tooManyAttempts($key, 3));
    }

    // =========================================================================
    // 8. Global Helper Functions Tests
    // =========================================================================

    public function testGlobalFoundationHelpers(): void
    {
        $cacheManager = new CacheManager(['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]]);
        CacheManager::setInstance($cacheManager);

        // cache() helper
        cache(['app_mode' => 'test']);
        $this->assertEquals('test', cache('app_mode'));

        // storage() helper
        $this->assertInstanceOf(StorageManager::class, storage());

        // auth() helper
        $this->assertInstanceOf(AuthManager::class, auth());

        // response_json() helper
        $res = response_json(['status' => 'ok']);
        $this->assertEquals(200, $res->getStatusCode());
    }
}

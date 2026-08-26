<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Database\Connection\Connection;
use Switch\Database\Connection\ConnectionConfig;
use Switch\Database\ORM\Model;
use Switch\Foundation\Api\RateLimiter;
use Switch\Foundation\Auth\AuthenticatableInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Facade\Auth;
use Switch\Foundation\Auth\Passwordless\Exception\InvalidTokenException;
use Switch\Foundation\Auth\Passwordless\Exception\TokenExpiredException;
use Switch\Foundation\Auth\Passwordless\Exception\TooManyRequestsException;
use Switch\Foundation\Auth\Passwordless\Exception\UserNotFoundException;
use Switch\Foundation\Auth\Passwordless\HasPasswordlessAuth;
use Switch\Foundation\Auth\Passwordless\Mail\MagicLinkMailable;
use Switch\Foundation\Auth\Passwordless\PasswordlessController;
use Switch\Foundation\Auth\Passwordless\PasswordlessManager;
use Switch\Foundation\Auth\Passwordless\PasswordlessRoutes;
use Switch\Foundation\Auth\Passwordless\PasswordlessToken;
use Switch\Foundation\Cache\CacheManager;
use Switch\Foundation\Mailer\MailManager;
use Switch\Foundation\Mailer\Transport\ArrayTransport;
use Switch\Http\ServerRequest;
use Switch\Router\Facade\Route;
use Switch\Router\Router;

// Test User Model for Passwordless Auth
class PasswordlessTestUser extends Model implements AuthenticatableInterface
{
    use HasPasswordlessAuth;

    protected string $table = 'test_passwordless_users';
    protected string $primaryKey = 'id';
    protected array $fillable = ['name', 'email', 'password', 'remember_token'];
}

class PasswordlessTest extends TestCase
{
    private Connection $db;
    private ArrayTransport $mailTransport;
    private PasswordlessManager $manager;

    protected function setUp(): void
    {
        // 1. In-memory SQLite setup
        $config = new ConnectionConfig(driver: 'sqlite', database: ':memory:');
        $this->db = new Connection($config);
        Model::setConnection($this->db);

        // 2. Create tables
        $this->db->statement("
            CREATE TABLE IF NOT EXISTS test_passwordless_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NULL,
                remember_token TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
        ");

        $this->db->statement("
            CREATE TABLE IF NOT EXISTS passwordless_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                token TEXT NOT NULL UNIQUE,
                type TEXT NOT NULL DEFAULT 'login',
                payload TEXT NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            );
        ");

        // 3. Setup Mailer with ArrayTransport
        $this->mailTransport = new ArrayTransport();
        $mailManager = new MailManager(['default' => 'array']);
        $mailManager->setTransport('array', $this->mailTransport);
        MailManager::setInstance($mailManager);

        // 4. Setup Cache & RateLimiter
        CacheManager::setInstance(new CacheManager(['default' => 'array']));
        $rateLimiter = new RateLimiter(CacheManager::getInstance());
        RateLimiter::setInstance($rateLimiter);

        // 5. Setup AuthManager
        $authManager = new AuthManager([
            'default' => 'web',
            'guards' => [
                'web' => [
                    'driver' => 'session',
                    'provider' => 'users',
                ],
            ],
            'providers' => [
                'users' => [
                    'model' => PasswordlessTestUser::class,
                ],
            ],
        ]);
        AuthManager::setInstance($authManager);

        // 6. Setup PasswordlessManager
        $this->manager = new PasswordlessManager([
            'app_url' => 'https://example.com',
            'app_name' => 'Switch Test App',
            'user_model' => PasswordlessTestUser::class,
            'token_expiry' => 15,
            'recovery_expiry' => 60,
            'token_length' => 32,
            'rate_limit' => [
                'enabled' => true,
                'max_attempts' => 3,
                'decay_seconds' => 300,
            ],
        ], $rateLimiter, $mailManager);
        PasswordlessManager::setInstance($this->manager);
    }

    public function testTokenModelLifecycle(): void
    {
        $token = PasswordlessToken::create([
            'email' => 'alice@example.com',
            'token' => 'valid_secret_token_123',
            'type' => PasswordlessToken::TYPE_LOGIN,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        ]);

        $this->assertTrue($token->isValid());
        $this->assertFalse($token->isExpired());
        $this->assertFalse($token->isUsed());

        $token->markUsed();

        $this->assertTrue($token->isUsed());
        $this->assertFalse($token->isValid());
    }

    public function testTokenModelExpiredCheck(): void
    {
        $expiredToken = PasswordlessToken::create([
            'email' => 'bob@example.com',
            'token' => 'expired_token_456',
            'type' => PasswordlessToken::TYPE_LOGIN,
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        ]);

        $this->assertTrue($expiredToken->isExpired());
        $this->assertFalse($expiredToken->isValid());
    }

    public function testSendLoginLinkSuccess(): void
    {
        PasswordlessTestUser::create([
            'name' => 'Alice Dev',
            'email' => 'alice@example.com',
        ]);

        $result = $this->manager->sendLoginLink('alice@example.com');
        $this->assertTrue((bool) $result);

        $sentMails = $this->mailTransport->messages();
        $this->assertCount(1, $sentMails);

        /** @var MagicLinkMailable $mail */
        $mail = $sentMails[0];
        $this->assertStringContainsString('Magic Login Link', $mail->getSubject());
        $this->assertStringContainsString('https://example.com/auth/verify/', $mail->getVerifyUrl());

        // Check token recorded in DB
        $token = PasswordlessToken::where('email', '=', 'alice@example.com')->first();
        $this->assertNotNull($token);
        $this->assertEquals(PasswordlessToken::TYPE_LOGIN, $token->type);
        $this->assertTrue($token->isValid());
    }

    public function testSendLoginLinkFailsWhenUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->manager->sendLoginLink('nonexistent@example.com');
    }

    public function testSendRegistrationLinkWithPayload(): void
    {
        $result = $this->manager->sendRegistrationLink('newuser@example.com', [
            'name' => 'New User',
            'role' => 'editor',
        ]);

        $this->assertTrue((bool) $result);

        $sentMails = $this->mailTransport->messages();
        $this->assertCount(1, $sentMails);

        $token = PasswordlessToken::where('email', '=', 'newuser@example.com')->first();
        $this->assertNotNull($token);
        $this->assertEquals(PasswordlessToken::TYPE_REGISTER, $token->type);
        $this->assertEquals('New User', $token->payload['name']);
        $this->assertEquals('editor', $token->payload['role']);
    }

    public function testSendRecoveryLinkSuccess(): void
    {
        PasswordlessTestUser::create([
            'name' => 'Charlie Dev',
            'email' => 'charlie@example.com',
        ]);

        $result = $this->manager->sendRecoveryLink('charlie@example.com');
        $this->assertTrue((bool) $result);

        $sentMails = $this->mailTransport->messages();
        $this->assertCount(1, $sentMails);

        $token = PasswordlessToken::where('email', '=', 'charlie@example.com')->first();
        $this->assertNotNull($token);
        $this->assertEquals(PasswordlessToken::TYPE_RECOVERY, $token->type);
    }

    public function testVerifyTokenValidation(): void
    {
        $token = $this->manager->generateToken('dave@example.com', PasswordlessToken::TYPE_LOGIN);

        // 1. Valid token passes
        $verified = $this->manager->verifyToken($token->token, PasswordlessToken::TYPE_LOGIN);
        $this->assertEquals($token->id, $verified->id);

        // 2. Type mismatch throws exception
        $this->expectException(InvalidTokenException::class);
        $this->manager->verifyToken($token->token, PasswordlessToken::TYPE_REGISTER);
    }

    public function testVerifyTokenExpiredThrowsException(): void
    {
        $token = PasswordlessToken::create([
            'email' => 'expired@example.com',
            'token' => 'expired_token_str',
            'type' => PasswordlessToken::TYPE_LOGIN,
            'expires_at' => date('Y-m-d H:i:s', time() - 100),
        ]);

        $this->expectException(TokenExpiredException::class);
        $this->manager->verifyToken('expired_token_str');
    }

    public function testAuthenticateLoginFlow(): void
    {
        $user = PasswordlessTestUser::create([
            'name' => 'Emma Watson',
            'email' => 'emma@example.com',
        ]);

        $token = $this->manager->generateToken('emma@example.com', PasswordlessToken::TYPE_LOGIN);

        $authUser = $this->manager->authenticate($token->token);

        $this->assertInstanceOf(AuthenticatableInterface::class, $authUser);
        $this->assertEquals($user->id, $authUser->getAuthIdentifier());

        // Token should be marked as used
        $reloadedToken = PasswordlessToken::find($token->id);
        $this->assertTrue($reloadedToken->isUsed());

        // Attempting to reuse should fail
        $this->expectException(InvalidTokenException::class);
        $this->manager->authenticate($token->token);
    }

    public function testAuthenticateRegistrationFlowCreatesUser(): void
    {
        $token = $this->manager->generateToken('newbie@example.com', PasswordlessToken::TYPE_REGISTER, [
            'name' => 'Newbie Programmer',
        ]);

        $this->assertNull(PasswordlessTestUser::where('email', '=', 'newbie@example.com')->first());

        $user = $this->manager->authenticate($token->token);

        $this->assertInstanceOf(AuthenticatableInterface::class, $user);
        $this->assertEquals('newbie@example.com', $user->email);
        $this->assertEquals('Newbie Programmer', $user->name);

        // User should now exist in DB
        $dbUser = PasswordlessTestUser::where('email', '=', 'newbie@example.com')->first();
        $this->assertNotNull($dbUser);
        $this->assertEquals($user->id, $dbUser->id);
    }

    public function testRateLimitingBlocksExcessiveRequests(): void
    {
        PasswordlessTestUser::create([
            'name' => 'Frankie',
            'email' => 'frankie@example.com',
        ]);

        // Max attempts configured is 3
        $this->manager->sendLoginLink('frankie@example.com');
        $this->manager->sendLoginLink('frankie@example.com');
        $this->manager->sendLoginLink('frankie@example.com');

        // 4th attempt should throw TooManyRequestsException
        $this->expectException(TooManyRequestsException::class);
        $this->manager->sendLoginLink('frankie@example.com');
    }

    public function testCleanExpiredTokens(): void
    {
        PasswordlessToken::create([
            'email' => 'old1@example.com',
            'token' => 'old1',
            'expires_at' => date('Y-m-d H:i:s', time() - 3600),
        ]);
        PasswordlessToken::create([
            'email' => 'old2@example.com',
            'token' => 'old2',
            'expires_at' => date('Y-m-d H:i:s', time() - 1800),
        ]);
        PasswordlessToken::create([
            'email' => 'fresh@example.com',
            'token' => 'fresh',
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        ]);

        $deletedCount = $this->manager->cleanExpiredTokens();
        $this->assertEquals(2, $deletedCount);
        $this->assertCount(1, PasswordlessToken::all());
    }

    public function testControllerRequestLoginJsonAndHtml(): void
    {
        PasswordlessTestUser::create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
        ]);

        $controller = new PasswordlessController($this->manager);

        // 1. HTML request
        $request = (new ServerRequest('POST', '/auth/login'))
            ->withParsedBody(['email' => 'grace@example.com']);

        $response = $controller->requestLogin($request);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/auth/link-sent', $response->getHeaderLine('Location'));

        // 2. JSON API request
        $jsonRequest = (new ServerRequest('POST', '/auth/login'))
            ->withHeader('Accept', 'application/json')
            ->withParsedBody(['email' => 'grace@example.com']);

        $jsonResponse = $controller->requestLogin($jsonRequest);
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('application/json', $jsonResponse->getHeaderLine('Content-Type'));
        $data = json_decode((string) $jsonResponse->getBody(), true);
        $this->assertTrue($data['success']);
    }

    public function testControllerVerifyToken(): void
    {
        $user = PasswordlessTestUser::create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);

        $token = $this->manager->generateToken('ada@example.com', PasswordlessToken::TYPE_LOGIN);

        $controller = new PasswordlessController($this->manager);

        $request = (new ServerRequest('GET', "/auth/verify/{$token->token}"))
            ->withHeader('Accept', 'application/json')
            ->withAttribute('token', $token->token);

        $response = $controller->verify($token->token, $request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($user->id, $data['user']['id']);
    }

    public function testPasswordlessRoutesRegister(): void
    {
        $router = new Router();
        Route::setRouter($router);

        PasswordlessRoutes::register(PasswordlessController::class, [
            'prefix' => '/auth',
            'name_prefix' => 'auth.',
        ]);

        $this->assertEquals('/auth/login', $router->url('auth.login'));
        $this->assertEquals('/auth/register', $router->url('auth.register'));
        $this->assertEquals('/auth/recover', $router->url('auth.recover'));
        $this->assertEquals('/auth/verify/xyz123', $router->url('auth.verify', ['token' => 'xyz123']));
        $this->assertEquals('/auth/logout', $router->url('auth.logout'));
    }

    public function testAuthFacadePasswordlessIntegration(): void
    {
        PasswordlessTestUser::create([
            'name' => 'Linus Torvalds',
            'email' => 'linus@example.com',
        ]);

        $this->assertInstanceOf(PasswordlessManager::class, Auth::passwordless());

        $sent = Auth::sendLoginLink('linus@example.com');
        $this->assertTrue((bool) $sent);

        $this->assertCount(1, $this->mailTransport->messages());
    }

    public function testGlobalPasswordlessHelper(): void
    {
        $this->assertTrue(function_exists('passwordless'));
        $this->assertInstanceOf(PasswordlessManager::class, passwordless());
    }
}

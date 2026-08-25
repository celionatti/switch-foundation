<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use Switch\Foundation\Api\ApiResponse;
use Switch\Foundation\Testbench\TestCase as TestbenchTestCase;
use Switch\Router\Facade\Route;
use Switch\Router\Router;

class TestbenchTest extends TestbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = new Router();
        Route::setRouter($router);

        // Register test routes
        Route::get('/api/users', function () {
            return ApiResponse::success([
                ['id' => 1, 'name' => 'Alice', 'role' => 'admin'],
                ['id' => 2, 'name' => 'Bob', 'role' => 'member'],
            ]);
        });

        Route::post('/api/users', function ($request) {
            $data = $request->getParsedBody();
            return ApiResponse::created([
                'id' => 3,
                'name' => $data['name'] ?? 'Anonymous',
            ]);
        });

        Route::get('/html-page', function () {
            return "<html><body><h1>Welcome Alice</h1></body></html>";
        });
    }

    public function testGetJsonResponseAndStructure(): void
    {
        $this->get('/api/users')
            ->assertOk()
            ->assertJson()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'role'],
                ],
            ]);
    }

    public function testPostJsonCreatesResource(): void
    {
        $this->postJson('/api/users', ['name' => 'Charlie'])
            ->assertCreated()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.id', 3)
            ->assertJsonPath('data.name', 'Charlie');
    }

    public function testNotFoundEndpoint(): void
    {
        $this->get('/non-existent-path')
            ->assertNotFound();
    }

    public function testHtmlContentAssertions(): void
    {
        $this->get('/html-page')
            ->assertOk()
            ->assertSee('Welcome Alice')
            ->assertDontSee('Secret Password');
    }

    public function testHeadersAndTokenChaining(): void
    {
        $response = $this->withHeaders(['X-Client-Id' => 'switch_app'])
            ->withToken('mock_bearer_token')
            ->get('/api/users')
            ->assertOk();

        $this->assertTrue($response->json('success'));
    }
}

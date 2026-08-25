<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Context\Context;
use Switch\Foundation\Context\ContextManager;
use Switch\Foundation\Context\Facade\Context as ContextFacade;

class ContextTest extends TestCase
{
    protected function setUp(): void
    {
        ContextFacade::clear();
    }

    public function testContextBasicValueAndDotNotation(): void
    {
        $ctx = new Context('theme', ['mode' => 'dark', 'colors' => ['primary' => '#6366f1']]);

        $this->assertEquals('dark', $ctx->get('mode'));
        $this->assertEquals('#6366f1', $ctx->get('colors.primary'));
        $this->assertEquals('default', $ctx->get('nonexistent', 'default'));
        $this->assertTrue($ctx->has('mode'));
        $this->assertFalse($ctx->has('unknown'));
    }

    public function testContextStackScopingAndProvide(): void
    {
        $ctx = new Context('user', ['name' => 'Root']);

        $this->assertEquals('Root', $ctx->get('name'));

        // Nested scoped provide
        $result = $ctx->provide(['name' => 'Child'], function ($childVal, $context) {
            $this->assertEquals('Child', $context->get('name'));
            return 'rendered_child';
        });

        $this->assertEquals('rendered_child', $result);
        // Restored after callback execution
        $this->assertEquals('Root', $ctx->get('name'));
    }

    public function testContextMutateAndMerge(): void
    {
        $ctx = new Context('counter', ['count' => 0]);

        $ctx->mutate(fn($prev) => ['count' => $prev['count'] + 5]);
        $this->assertEquals(5, $ctx->get('count'));

        $ctx->merge(['step' => 1]);
        $this->assertEquals(5, $ctx->get('count'));
        $this->assertEquals(1, $ctx->get('step'));
    }

    public function testContextSubscribers(): void
    {
        $ctx = new Context('cart', ['total' => 0]);
        $notified = [];

        $unsubscribe = $ctx->subscribe(function ($newVal, $oldVal) use (&$notified) {
            $notified[] = $newVal['total'];
        });

        $ctx->set(['total' => 50]);
        $ctx->set(['total' => 120]);

        $this->assertEquals([50, 120], $notified);

        $unsubscribe();
        $ctx->set(['total' => 200]);
        $this->assertEquals([50, 120], $notified); // not called after unsubscribing
    }

    public function testContextManagerProvideMany(): void
    {
        $manager = new ContextManager();

        $result = $manager->provideMany([
            'theme' => 'dark',
            'auth' => ['id' => 42, 'role' => 'admin']
        ], function () use ($manager) {
            $this->assertEquals('dark', $manager->use('theme'));
            $this->assertEquals('admin', $manager->use('auth.role'));
            return 'scope_done';
        });

        $this->assertEquals('scope_done', $result);
        $this->assertNull($manager->use('theme'));
    }

    public function testContextFacadeAndGlobalHelper(): void
    {
        ContextFacade::provide('settings', ['timezone' => 'UTC']);

        $this->assertEquals('UTC', ContextFacade::use('settings.timezone'));
        $this->assertEquals('UTC', context('settings.timezone'));

        // Batch provide using helper
        context(['currency' => 'USD']);
        $this->assertEquals('USD', context('currency'));
    }

    public function testClientContextPayload(): void
    {
        $manager = new ContextManager();
        $manager->provide('cart', ['items' => 3]);
        $manager->provide('secret', ['token' => '123']);
        $manager->markClient('cart');

        $payload = $manager->getClientPayload();
        $this->assertEquals(['cart' => ['items' => 3]], $payload);
        $this->assertArrayNotHasKey('secret', $payload);
    }
}

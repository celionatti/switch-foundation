<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Flow\AuditTrail;
use Switch\Foundation\Flow\HasAuditTrail;
use Switch\Foundation\Flow\HasFlow;
use Switch\Foundation\Flow\StateMachine;
use Switch\Foundation\Flow\TransitionDeniedException;

// Sample Order Model for Flow testing
class TestOrder
{
    use HasFlow;
    use HasAuditTrail;

    public int $id = 42;
    public string $status = 'pending';
    public ?string $trackingNumber = null;
    public static array $transitionLog = [];

    public static function flow(): StateMachine
    {
        return StateMachine::define('status')
            ->states(['pending', 'processing', 'shipped', 'delivered', 'cancelled'])
            ->initial('pending')
            ->allow('pay', from: 'pending', to: 'processing')
            ->allow('ship', from: 'processing', to: 'shipped', guard: fn(TestOrder $order) => !empty($order->trackingNumber))
            ->allow('deliver', from: 'shipped', to: 'delivered')
            ->allow('cancel', from: ['pending', 'processing'], to: 'cancelled')
            ->onTransition('ship', function (TestOrder $order, $context, $from, $to) {
                self::$transitionLog[] = "Order #{$order->id} shipped with tracking {$order->trackingNumber}";
            });
    }
}

class FlowTest extends TestCase
{
    protected function setUp(): void
    {
        AuditTrail::clear();
        TestOrder::$transitionLog = [];
    }

    public function testInitialStateAndAvailableTransitions(): void
    {
        $order = new TestOrder();
        $this->assertEquals('pending', $order->state());
        $this->assertTrue($order->canApply('pay'));
        $this->assertTrue($order->canTransitionTo('processing'));
        $this->assertTrue($order->canApply('cancel'));
        $this->assertFalse($order->canApply('ship')); // cannot ship from pending
    }

    public function testSuccessfulTransitionLifecycle(): void
    {
        $order = new TestOrder();

        // 1. Pay
        $order->applyFlow('pay');
        $this->assertEquals('processing', $order->state());

        // 2. Try to ship without tracking -> should be rejected by guard
        $this->assertFalse($order->canApply('ship'));

        $this->expectException(TransitionDeniedException::class);
        $order->applyFlow('ship');
    }

    public function testGuardedTransitionPassesWithContext(): void
    {
        $order = new TestOrder();
        $order->applyFlow('pay');

        // Set tracking number
        $order->trackingNumber = 'TRACK-998877';
        $this->assertTrue($order->canApply('ship'));

        // Apply ship
        $order->applyFlow('ship');
        $this->assertEquals('shipped', $order->state());

        // Check transition callback was triggered
        $this->assertCount(1, TestOrder::$transitionLog);
        $this->assertEquals('Order #42 shipped with tracking TRACK-998877', TestOrder::$transitionLog[0]);

        // Deliver
        $order->transitionTo('delivered');
        $this->assertEquals('delivered', $order->state());
    }

    public function testAuditTrailRecordsTransitions(): void
    {
        $order = new TestOrder();
        $order->applyFlow('pay', ['source' => 'stripe_checkout']);

        $audits = $order->audits();
        $this->assertCount(1, $audits);
        $this->assertEquals('state_transition', $audits[0]['event']);
        $this->assertEquals('pending', $audits[0]['meta']['from']);
        $this->assertEquals('processing', $audits[0]['meta']['to']);
        $this->assertEquals('stripe_checkout', $audits[0]['meta']['context']['source']);
    }
}

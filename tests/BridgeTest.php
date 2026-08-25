<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Bridge\Facade\Bridge;
use Switch\Foundation\Bridge\WebhookDispatcher;
use Switch\Foundation\Bridge\WebhookJob;
use Switch\Foundation\Bridge\WebhookReceiver;
use Switch\Foundation\Bridge\WebhookSignatureException;
use Switch\Foundation\Queue\Driver\ArrayDriver;
use Switch\Foundation\Queue\QueueManager;
use Switch\Http\ServerRequest;
use Switch\Http\Stream;
use Switch\Http\Uri;

class BridgeTest extends TestCase
{
    protected function setUp(): void
    {
        WebhookReceiver::clearIdempotencyCache();
        WebhookDispatcher::clearHistory();

        $manager = new QueueManager([
            'default' => 'array',
            'connections' => ['array' => ['driver' => 'array']],
        ]);
        QueueManager::setInstance($manager);
    }

    public function testVerifyIncomingWebhookSuccess(): void
    {
        $secret = 'whsec_test_secret_12345';
        $payload = ['event' => 'payment.succeeded', 'amount' => 5000];
        $body = json_encode($payload);
        $timestamp = time();

        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        $headerValue = "t={$timestamp},v1={$signature}";

        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/webhooks/payments'),
            headers: [
                'Content-Type' => 'application/json',
                'X-Switch-Signature' => $headerValue,
            ],
            body: Stream::create($body)
        );

        $verified = Bridge::receive($request, $secret);

        $this->assertEquals('payment.succeeded', $verified['event']);
        $this->assertEquals(5000, $verified['amount']);
    }

    public function testVerifyIncomingWebhookFailsWithInvalidSignature(): void
    {
        $secret = 'whsec_valid_key';
        $body = json_encode(['event' => 'hack.attempt']);

        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/webhooks/payments'),
            headers: [
                'Content-Type' => 'application/json',
                'X-Switch-Signature' => 'sha256=invalid_hash_signature',
            ],
            body: Stream::create($body)
        );

        $this->expectException(WebhookSignatureException::class);
        WebhookReceiver::verify($request, $secret);
    }

    public function testReplayAttackRejectedBeyondTolerance(): void
    {
        $secret = 'whsec_key';
        $body = json_encode(['event' => 'old.event']);
        $expiredTimestamp = time() - 600; // 10 minutes ago (exceeds 300s default)

        $signature = hash_hmac('sha256', "{$expiredTimestamp}.{$body}", $secret);

        $request = new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/webhooks/payments'),
            headers: [
                'X-Switch-Signature' => "t={$expiredTimestamp},v1={$signature}",
            ],
            body: Stream::create($body)
        );

        $this->expectException(WebhookSignatureException::class);
        WebhookReceiver::verify($request, $secret, toleranceSeconds: 300);
    }

    public function testIdempotencyKeyBlocksDuplicateDelivery(): void
    {
        $secret = 'whsec_key';
        $body = json_encode(['event' => 'invoice.paid']);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        $createRequest = fn() => new ServerRequest(
            method: 'POST',
            uri: new Uri('http://localhost/webhooks/payments'),
            headers: [
                'X-Switch-Signature' => "t={$timestamp},v1={$signature}",
                'X-Idempotency-Key' => 'idemp_unique_abc_123',
            ],
            body: Stream::create($body)
        );

        // First delivery passes
        $verified = WebhookReceiver::verify($createRequest(), $secret);
        $this->assertEquals('invoice.paid', $verified['event']);

        // Duplicate delivery with same idempotency key fails
        $this->expectException(WebhookSignatureException::class);
        WebhookReceiver::verify($createRequest(), $secret);
    }

    public function testOutboundWebhookDispatchAndSignature(): void
    {
        $intercepted = null;
        WebhookJob::$httpClient = function ($url, $body, $headers) use (&$intercepted) {
            $intercepted = [
                'url' => $url,
                'body' => $body,
                'headers' => $headers,
            ];
            return true;
        };

        $job = Bridge::dispatch(
            url: 'https://api.partner.com/webhooks',
            payload: ['order_id' => 999, 'status' => 'completed'],
            secret: 'outbound_secret_key',
            headers: ['X-Custom-Org' => 'Switch-Corp']
        );

        // Execute job
        $job->handle();

        $this->assertNotNull($intercepted);
        $this->assertEquals('https://api.partner.com/webhooks', $intercepted['url']);
        $this->assertArrayHasKey('X-Switch-Signature', $intercepted['headers']);
        $this->assertEquals('Switch-Corp', $intercepted['headers']['X-Custom-Org']);

        WebhookJob::$httpClient = null;
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Bridge;

use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Cache\Facade\Cache;

class WebhookReceiver
{
    /**
     * @var array<string, bool> In-memory processed idempotency keys
     */
    private static array $processedKeys = [];

    /**
     * Verify an incoming webhook request signature and extract verified payload.
     *
     * @param ServerRequestInterface $request
     * @param string $secret HMAC Secret key
     * @param string $headerName Signature header name
     * @param int $toleranceSeconds Maximum allowed timestamp drift in seconds (0 to disable)
     * @return array<string, mixed> Verified JSON payload array
     * @throws WebhookSignatureException
     */
    public static function verify(
        ServerRequestInterface $request,
        string $secret,
        string $headerName = 'X-Switch-Signature',
        int $toleranceSeconds = 300
    ): array {
        $body = (string) $request->getBody();
        if ($body === '') {
            $body = json_encode($request->getParsedBody() ?: []);
        }

        $sigHeader = $request->getHeaderLine($headerName);
        if ($sigHeader === '') {
            // Also check alternate common headers
            $sigHeader = $request->getHeaderLine('Stripe-Signature')
                ?: ($request->getHeaderLine('X-Hub-Signature-256')
                ?: $request->getHeaderLine('X-Signature'));
        }

        if ($sigHeader === '') {
            throw new WebhookSignatureException("Missing webhook signature header [{$headerName}].");
        }

        // Parse signature header: supports "t=timestamp,v1=hash" or "sha256=hash" or "hash"
        $timestamp = null;
        $signature = null;

        if (str_contains($sigHeader, 't=') || str_contains($sigHeader, 'v1=')) {
            $parts = explode(',', $sigHeader);
            foreach ($parts as $part) {
                [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
                if ($k === 't') {
                    $timestamp = (int) $v;
                } elseif ($k === 'v1' || $k === 'v0' || $k === 'sig') {
                    $signature = $v;
                }
            }
        } elseif (str_starts_with($sigHeader, 'sha256=')) {
            $signature = substr($sigHeader, 7);
        } else {
            $signature = $sigHeader;
        }

        if (!$signature) {
            throw new WebhookSignatureException("Invalid signature header format.");
        }

        // Replay attack verification
        if ($timestamp !== null && $toleranceSeconds > 0) {
            $drift = abs(time() - $timestamp);
            if ($drift > $toleranceSeconds) {
                throw new WebhookSignatureException("Webhook timestamp [{$timestamp}] exceeds tolerance window of {$toleranceSeconds}s.");
            }
        }

        // Compute expected signature
        $payloadToSign = $timestamp !== null ? "{$timestamp}.{$body}" : $body;
        $expectedSignature = hash_hmac('sha256', $payloadToSign, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            // Also try signing body directly as fallback
            $fallbackExpected = hash_hmac('sha256', $body, $secret);
            if (!hash_equals($fallbackExpected, $signature)) {
                throw new WebhookSignatureException("Webhook signature verification failed.");
            }
        }

        // Check Idempotency Key
        $idempotencyKey = $request->getHeaderLine('X-Idempotency-Key')
            ?: ($request->getHeaderLine('Idempotency-Key') ?: null);

        if ($idempotencyKey !== null && self::isProcessed($idempotencyKey)) {
            throw new WebhookSignatureException("Duplicate webhook delivery: Idempotency key [{$idempotencyKey}] already processed.");
        }

        if ($idempotencyKey !== null) {
            self::markProcessed($idempotencyKey);
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if an idempotency key has already been processed.
     */
    public static function isProcessed(string $key): bool
    {
        if (isset(self::$processedKeys[$key])) {
            return true;
        }

        if (class_exists(Cache::class)) {
            try {
                return Cache::has('webhook_idemp_' . $key);
            } catch (\Throwable) {
                // Ignore
            }
        }

        return false;
    }

    /**
     * Mark an idempotency key as processed.
     */
    public static function markProcessed(string $key, int $ttlSeconds = 86400): void
    {
        self::$processedKeys[$key] = true;

        if (class_exists(Cache::class)) {
            try {
                Cache::put('webhook_idemp_' . $key, true, $ttlSeconds);
            } catch (\Throwable) {
                // Ignore
            }
        }
    }

    /**
     * Clear idempotency cache.
     */
    public static function clearIdempotencyCache(): void
    {
        self::$processedKeys = [];
    }
}

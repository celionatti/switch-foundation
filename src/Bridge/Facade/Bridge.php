<?php

declare(strict_types=1);

namespace Switch\Foundation\Bridge\Facade;

use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Bridge\WebhookDispatcher;
use Switch\Foundation\Bridge\WebhookJob;
use Switch\Foundation\Bridge\WebhookReceiver;

class Bridge
{
    /**
     * Verify an incoming signed webhook request.
     *
     * @param ServerRequestInterface $request
     * @param string $secret
     * @param string $headerName
     * @param int $toleranceSeconds
     * @return array<string, mixed>
     */
    public static function receive(
        ServerRequestInterface $request,
        string $secret,
        string $headerName = 'X-Switch-Signature',
        int $toleranceSeconds = 300
    ): array {
        return WebhookReceiver::verify($request, $secret, $headerName, $toleranceSeconds);
    }

    /**
     * Dispatch an outbound webhook to an external URL.
     *
     * @param string $url
     * @param array<string, mixed> $payload
     * @param string $secret
     * @param array<string, string> $headers
     * @param array<string, mixed> $options
     * @return WebhookJob
     */
    public static function dispatch(
        string $url,
        array $payload,
        string $secret = '',
        array $headers = [],
        array $options = []
    ): WebhookJob {
        return WebhookDispatcher::dispatch($url, $payload, $secret, $headers, $options);
    }
}

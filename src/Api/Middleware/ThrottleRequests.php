<?php

declare(strict_types=1);

namespace Switch\Foundation\Api\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Foundation\Api\RateLimiter;
use Switch\Http\Response;

class ThrottleRequests implements MiddlewareInterface
{
    private int $maxAttempts;
    private int $decaySeconds;
    private RateLimiter $limiter;

    public function __construct(int $maxAttempts = 60, int $decaySeconds = 60, ?RateLimiter $limiter = null)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decaySeconds = $decaySeconds;
        $this->limiter = $limiter ?? RateLimiter::getInstance();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            $retryAfter = $this->limiter->availableIn($key);

            $payload = json_encode([
                'error' => true,
                'message' => 'Too Many Requests',
                'retry_after' => $retryAfter,
            ]);
            $body = class_exists(\Switch\Http\Stream::class) ? \Switch\Http\Stream::create($payload) : null;

            return new Response(429, [
                'Content-Type' => 'application/json',
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ], $body);
        }

        $this->limiter->hit($key, $this->decaySeconds);

        $response = $handler->handle($request);
        $remaining = $this->limiter->remaining($key, $this->maxAttempts);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) $remaining);
    }

    protected function resolveRequestSignature(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $ip = $serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
        $user = $request->getAttribute('user');
        $userId = is_object($user) && method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : null;

        $uri = $request->getUri()->getPath();

        return ($userId !== null ? 'user:' . $userId : 'ip:' . $ip) . '|' . $uri;
    }
}

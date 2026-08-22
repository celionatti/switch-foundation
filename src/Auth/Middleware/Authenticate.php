<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Switch\Foundation\Auth\AuthManager;
use Switch\Http\Response;

class Authenticate implements MiddlewareInterface
{
    private string $redirectTo;
    private array $guards;

    public function __construct(string $redirectTo = '/login', array $guards = ['web'])
    {
        $this->redirectTo = $redirectTo;
        $this->guards = $guards;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = AuthManager::getInstance();

        $authenticated = false;
        foreach ($this->guards as $guardName) {
            $guard = $auth->guard($guardName);
            if (method_exists($guard, 'setRequest')) {
                $guard->setRequest($request);
            }

            if ($guard->check()) {
                $authenticated = true;
                $request = $request->withAttribute('user', $guard->user());
                break;
            }
        }

        if (!$authenticated) {
            // If AJAX / JSON / API request, return 401 Unauthorized
            $accept = $request->getHeaderLine('Accept');
            if (str_contains($accept, 'application/json') || $request->hasHeader('Authorization')) {
                $payload = json_encode(['error' => true, 'message' => 'Unauthenticated.']);
                $body = class_exists(\Switch\Http\Stream::class) ? \Switch\Http\Stream::create($payload) : null;
                return new Response(401, ['Content-Type' => 'application/json'], $body);
            }

            return new Response(302, ['Location' => $this->redirectTo]);
        }

        return $handler->handle($request);
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Foundation\Auth\AuthManager;
use Switch\Http\Response;

class RedirectIfAuthenticated implements MiddlewareInterface
{
    private string $redirectTo;
    private array $guards;

    public function __construct(string $redirectTo = '/dashboard', array $guards = ['web'])
    {
        $this->redirectTo = $redirectTo;
        $this->guards = $guards;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $auth = AuthManager::getInstance();

        foreach ($this->guards as $guardName) {
            $guard = $auth->guard($guardName);
            if (method_exists($guard, 'setRequest')) {
                $guard->setRequest($request);
            }

            if ($guard->check()) {
                return new Response(302, ['Location' => $this->redirectTo]);
            }
        }

        return $handler->handle($request);
    }
}

<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\Foundation\Auth\Access\Gate;
use Switch\Http\Response;

class Authorize implements MiddlewareInterface
{
    private string $ability;
    private array $arguments;

    public function __construct(string $ability, array $arguments = [])
    {
        $this->ability = $ability;
        $this->arguments = $arguments;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (Gate::denies($this->ability, ...$this->arguments)) {
            $payload = json_encode([
                'error' => true,
                'message' => 'This action is unauthorized.'
            ]);
            $body = class_exists(\Switch\Http\Stream::class) ? \Switch\Http\Stream::create($payload) : null;
            return new Response(403, ['Content-Type' => 'application/json'], $body);
        }

        return $handler->handle($request);
    }
}

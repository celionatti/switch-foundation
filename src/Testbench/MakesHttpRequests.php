<?php

declare(strict_types=1);

namespace Switch\Foundation\Testbench;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Auth\Facade\Auth;
use Switch\Http\Response;
use Switch\Http\ServerRequest;
use Switch\Http\Stream;
use Switch\Http\Uri;
use Switch\Router\Facade\Route;

trait MakesHttpRequests
{
    protected array $defaultHeaders = [];
    protected array $serverVariables = [];
    protected ?object $authenticatedUser = null;

    /**
     * Set request headers for subsequent requests.
     */
    public function withHeaders(array $headers): static
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    public function withHeader(string $name, string $value): static
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeader('Authorization', "{$type} {$token}");
    }

    /**
     * Authenticate as a specific user for subsequent requests.
     */
    public function actingAs(object $user, ?string $guard = null): static
    {
        $this->authenticatedUser = $user;
        if (class_exists(Auth::class)) {
            try {
                Auth::login($user);
            } catch (\Throwable) {
                // Fallback
            }
        }
        return $this;
    }

    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return $this->call('POST', $uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    public function putJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return $this->call('PUT', $uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    public function patchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return $this->call('PATCH', $uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    public function deleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        return $this->call('DELETE', $uri, $data, $headers);
    }

    /**
     * Dispatch simulated HTTP request and wrap in TestResponse.
     */
    public function call(string $method, string $uri, array $data = [], array $headers = []): TestResponse
    {
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $body = null;
        $parsedBody = null;

        $isJson = isset($allHeaders['Content-Type']) && str_contains($allHeaders['Content-Type'], 'json');

        if ($isJson && !empty($data)) {
            $jsonString = json_encode($data);
            $body = class_exists(Stream::class) ? Stream::create($jsonString) : null;
            $parsedBody = $data;
        } elseif (!empty($data)) {
            $parsedBody = $data;
        }

        $url = str_starts_with($uri, 'http') ? $uri : "http://localhost/" . ltrim($uri, '/');
        $request = new ServerRequest(
            method: strtoupper($method),
            uri: new Uri($url),
            headers: $allHeaders,
            body: $body
        );

        if ($parsedBody !== null) {
            $request = $request->withParsedBody($parsedBody);
        }

        $response = $this->handleRequest($request);

        return new TestResponse($response);
    }

    /**
     * Handle incoming simulated request through the router or kernel.
     */
    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        if (class_exists(Route::class)) {
            $router = Route::getRouter();
            try {
                $match = $router->match($request->getMethod(), $request->getUri()->getPath());
                $handler = $match->getHandler();

                foreach ($match->getParameters() as $k => $v) {
                    $request = $request->withAttribute($k, $v);
                }

                if (is_callable($handler)) {
                    $result = $handler($request, $match->getParameters());
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$cls, $mtd] = $handler;
                    $inst = is_object($cls) ? $cls : new $cls();
                    $result = $inst->$mtd($request, $match->getParameters());
                } elseif (is_string($handler) && class_exists($handler)) {
                    $inst = new $handler();
                    $result = $inst($request, $match->getParameters());
                } else {
                    $result = new Response(500, [], Stream::create('Invalid Handler'));
                }

                if ($result instanceof ResponseInterface) {
                    return $result;
                }

                if (is_array($result) || is_object($result)) {
                    return new Response(200, ['Content-Type' => 'application/json'], Stream::create(json_encode($result)));
                }

                return new Response(200, ['Content-Type' => 'text/html'], Stream::create((string) $result));

            } catch (\Switch\Router\Exception\RouteNotFoundException) {
                return new Response(404, [], Stream::create('404 Not Found'));
            } catch (\Switch\Router\Exception\MethodNotAllowedException $e) {
                return new Response(405, ['Allow' => implode(', ', $e->getAllowedMethods())], Stream::create('405 Method Not Allowed'));
            }
        }

        return new Response(200, [], Stream::create('OK'));
    }
}

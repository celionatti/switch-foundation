<?php

declare(strict_types=1);

namespace Switch\Foundation\Api;

use Psr\Http\Message\ResponseInterface;
use Switch\Http\Response;

class ApiResponse
{
    public static function json(array $payload, int $status = 200, array $headers = []): ResponseInterface
    {
        $headers['Content-Type'] = 'application/json';
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $body = class_exists(\Switch\Http\Stream::class) ? \Switch\Http\Stream::create($json) : null;
        return new Response($status, $headers, $body);
    }

    public static function success(mixed $data = null, string $message = 'Success', int $code = 200, array $meta = []): ResponseInterface
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data instanceof \JsonSerializable ? $data->jsonSerialize() : $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $code);
    }

    public static function created(mixed $data = null, string $message = 'Resource created successfully'): ResponseInterface
    {
        return self::success($data, $message, 201);
    }

    public static function error(string $message = 'An error occurred', int $code = 400, mixed $errors = null): ResponseInterface
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return self::json($payload, $code);
    }

    public static function notFound(string $message = 'Resource not found'): ResponseInterface
    {
        return self::error($message, 404);
    }

    public static function validation(mixed $errors = null, string $message = 'Validation failed'): ResponseInterface
    {
        return self::error($message, 422, $errors);
    }

    public static function unauthorized(string $message = 'Unauthenticated'): ResponseInterface
    {
        return self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden action'): ResponseInterface
    {
        return self::error($message, 403);
    }

    public static function validationError(array $errors, string $message = 'The given data was invalid.'): ResponseInterface
    {
        return self::error($message, 422, $errors);
    }
}

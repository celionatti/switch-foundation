<?php

declare(strict_types=1);

namespace Switch\Foundation\Action;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Api\ApiResponse;
use Switch\Foundation\Queue\Facade\Queue;
use Switch\Http\Response;
use Switch\Http\Stream;
use RuntimeException;

/**
 * Base Switch Action — The Quad-Engine (HTTP Controller + Queue Job + CLI Command + Service).
 */
abstract class Action
{
    /**
     * Determine if the current user is authorized to perform this action.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to this action.
     *
     * @return array<string, string|array>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get the middleware that should be applied to this action when executed as a controller.
     *
     * @return array<int, mixed>
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Execute the action as an HTTP Controller endpoint.
     */
    public function asController(ServerRequestInterface $request, array $routeParams = []): ResponseInterface
    {
        // 1. Authorization Check
        if (!$this->authorize()) {
            return ApiResponse::error('Forbidden: You are not authorized to perform this action.', 403);
        }

        // 2. Extract and merge input data (body, query, route parameters)
        $data = $this->extractRequestData($request, $routeParams);

        // 3. Validation Check
        $rules = $this->rules();
        if (!empty($rules)) {
            $validation = $this->validateData($data, $rules, $this->messages());
            if ($validation['failed']) {
                return ApiResponse::validation($validation['errors'], 'Validation failed');
            }
            $data = $validation['validated'];
        }

        // 4. Run the core handle() method
        $result = $this->handle($data, $request);

        // 5. Format Response
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        return ApiResponse::success($result);
    }

    /**
     * Invoke the action directly.
     */
    public function __invoke(mixed ...$arguments): mixed
    {
        // If invoked with a PSR-7 ServerRequest, treat as controller execution
        if (isset($arguments[0]) && $arguments[0] instanceof ServerRequestInterface) {
            return $this->asController($arguments[0], $arguments[1] ?? []);
        }

        return $this->handle(...$arguments);
    }

    /**
     * Statically execute the action directly as a domain service.
     */
    public static function run(mixed ...$arguments): mixed
    {
        $instance = new static();
        return $instance->handle(...$arguments);
    }

    /**
     * Dispatch the action asynchronously to the background queue.
     */
    public static function dispatch(mixed ...$arguments): ActionJob
    {
        $job = new ActionJob(static::class, $arguments);
        Queue::push($job);
        return $job;
    }

    /**
     * Dispatch the action to a specific queue.
     */
    public static function onQueue(string $queue, mixed ...$arguments): ActionJob
    {
        $job = (new ActionJob(static::class, $arguments))->onQueue($queue);
        Queue::push($job, $queue);
        return $job;
    }

    /**
     * Extract and consolidate inputs from request body, query params, and route params.
     */
    protected function extractRequestData(ServerRequestInterface $request, array $routeParams = []): array
    {
        $parsedBody = $request->getParsedBody();
        $body = is_array($parsedBody) ? $parsedBody : [];

        // If JSON payload in request body
        if (empty($body)) {
            $raw = (string) $request->getBody();
            if (!empty($raw) && str_starts_with(trim($raw), '{')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        }

        $query = $request->getQueryParams();

        return array_merge($query, $body, $routeParams);
    }

    /**
     * Perform validation against rules.
     */
    protected function validateData(array $data, array $rules, array $messages = []): array
    {
        if (class_exists(\Switch\Controller\Validation\Validator::class)) {
            try {
                $validated = \Switch\Controller\Validation\Validator::validate($data, $rules, $messages);
                return [
                    'failed' => false,
                    'errors' => [],
                    'validated' => $validated,
                ];
            } catch (\Switch\Controller\Validation\ValidationException $e) {
                return [
                    'failed' => true,
                    'errors' => $e->getErrors(),
                    'validated' => [],
                ];
            }
        }

        // Lightweight fallback validator
        $errors = [];
        $validated = [];

        foreach ($rules as $field => $fieldRules) {
            $ruleList = is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules;
            $val = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && ($val === null || $val === '')) {
                    $errors[$field][] = $messages["{$field}.required"] ?? "The {$field} field is required.";
                } elseif ($rule === 'email' && $val && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = $messages["{$field}.email"] ?? "The {$field} field must be a valid email.";
                } elseif ($rule === 'numeric' && $val && !is_numeric($val)) {
                    $errors[$field][] = $messages["{$field}.numeric"] ?? "The {$field} field must be numeric.";
                }
            }

            if (!isset($errors[$field]) && array_key_exists($field, $data)) {
                $validated[$field] = $data[$field];
            }
        }

        return [
            'failed' => !empty($errors),
            'errors' => $errors,
            'validated' => empty($errors) ? array_merge($data, $validated) : [],
        ];
    }
}

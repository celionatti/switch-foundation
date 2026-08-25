<?php

declare(strict_types=1);

namespace Switch\Foundation\Api\AutoCrud;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Foundation\Api\ApiResponse;
use Switch\Database\ORM\Model;
use RuntimeException;

/**
 * Instant REST API Auto-CRUD Controller.
 */
class AutoCrudController
{
    /**
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $options
     */
    public function __construct(
        protected string $modelClass,
        protected array $options = []
    ) {
        if (!class_exists($this->modelClass)) {
            throw new RuntimeException("Model class '{$this->modelClass}' not found for AutoCrudController.");
        }
    }

    /**
     * GET / - List records with dynamic filtering, sorting, searching, and pagination.
     */
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        $query = ($this->modelClass)::query();

        $filter = QueryFilter::for($query, $request);

        if (!empty($this->options['filters'])) {
            $filter->allowedFilters($this->options['filters']);
        }
        if (!empty($this->options['sorts'])) {
            $filter->allowedSorts($this->options['sorts']);
        }
        if (!empty($this->options['includes'])) {
            $filter->allowedIncludes($this->options['includes']);
        }
        if (!empty($this->options['searchable'])) {
            $filter->searchable($this->options['searchable']);
        }
        if (!empty($this->options['per_page'])) {
            $filter->defaultPerPage((int) $this->options['per_page']);
        }

        $result = $filter->paginate();

        return ApiResponse::success(
            data: $result['data'],
            message: 'Records retrieved successfully',
            code: 200,
            meta: $result['meta']
        );
    }

    /**
     * GET /{id} - Show single record by ID.
     */
    public function show(ServerRequestInterface $request, array $params = []): ResponseInterface
    {
        $id = $params['id'] ?? null;
        if ($id === null) {
            return ApiResponse::error('Record ID missing', 400);
        }

        $query = ($this->modelClass)::query();

        $includes = $request->getQueryParams()['include'] ?? null;
        if ($includes && method_exists($query, 'with')) {
            $relations = array_map('trim', explode(',', $includes));
            $query->with(...$relations);
        }

        $record = $query->where('id', '=', $id)->first();

        if (!$record) {
            return ApiResponse::error('Record not found', 404);
        }

        return ApiResponse::success(
            data: $record instanceof Model ? $record->toArray() : $record,
            message: 'Record retrieved successfully'
        );
    }

    /**
     * POST / - Store a new record.
     */
    public function store(ServerRequestInterface $request): ResponseInterface
    {
        $data = $this->extractInputData($request);

        // Validation
        $rules = $this->options['rules'] ?? [];
        if (empty($rules) && property_exists($this->modelClass, 'rules')) {
            $rules = ($this->modelClass)::$rules;
        }

        if (!empty($rules)) {
            $validation = $this->validate($data, $rules);
            if ($validation['failed']) {
                return ApiResponse::validation($validation['errors'], 'Validation failed');
            }
            $data = $validation['validated'];
        }

        $record = ($this->modelClass)::create($data);

        return ApiResponse::success(
            data: $record instanceof Model ? $record->toArray() : $record,
            message: 'Record created successfully',
            code: 201
        );
    }

    /**
     * PUT/PATCH /{id} - Update an existing record.
     */
    public function update(ServerRequestInterface $request, array $params = []): ResponseInterface
    {
        $id = $params['id'] ?? null;
        if ($id === null) {
            return ApiResponse::error('Record ID missing', 400);
        }

        $record = ($this->modelClass)::find($id);
        if (!$record) {
            return ApiResponse::error('Record not found', 404);
        }

        $data = $this->extractInputData($request);

        // Validation
        $rules = $this->options['update_rules'] ?? ($this->options['rules'] ?? []);
        if (!empty($rules)) {
            // If partial update, only validate keys present in payload
            $updateRules = isset($this->options['update_rules'])
                ? $this->options['update_rules']
                : array_intersect_key($rules, $data);

            $validation = $this->validate($data, $updateRules);
            if ($validation['failed']) {
                return ApiResponse::validation($validation['errors'], 'Validation failed');
            }
            $data = $validation['validated'];
        }

        $record->fill($data);
        $record->save();

        return ApiResponse::success(
            data: $record instanceof Model ? $record->toArray() : $record,
            message: 'Record updated successfully',
            code: 200
        );
    }

    /**
     * DELETE /{id} - Delete a record.
     */
    public function destroy(ServerRequestInterface $request, array $params = []): ResponseInterface
    {
        $id = $params['id'] ?? null;
        if ($id === null) {
            return ApiResponse::error('Record ID missing', 400);
        }

        $record = ($this->modelClass)::find($id);
        if (!$record) {
            return ApiResponse::error('Record not found', 404);
        }

        $record->delete();

        return ApiResponse::success(
            data: null,
            message: 'Record deleted successfully',
            code: 200
        );
    }

    protected function extractInputData(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body) && !empty($body)) {
            return $body;
        }

        $raw = (string) $request->getBody();
        if (!empty($raw) && str_starts_with(trim($raw), '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function validate(array $data, array $rules): array
    {
        if (class_exists(\Switch\Controller\Validation\Validator::class)) {
            try {
                $validated = \Switch\Controller\Validation\Validator::validate($data, $rules);
                return ['failed' => false, 'errors' => [], 'validated' => $validated];
            } catch (\Switch\Controller\Validation\ValidationException $e) {
                return ['failed' => true, 'errors' => $e->getErrors(), 'validated' => []];
            }
        }

        return ['failed' => false, 'errors' => [], 'validated' => $data];
    }
}

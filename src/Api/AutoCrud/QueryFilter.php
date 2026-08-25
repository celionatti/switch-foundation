<?php

declare(strict_types=1);

namespace Switch\Foundation\Api\AutoCrud;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Universal Query Filter & Parser for REST APIs.
 */
class QueryFilter
{
    protected mixed $query;
    protected array $params = [];
    protected array $allowedFilters = [];
    protected array $allowedSorts = [];
    protected array $allowedIncludes = [];
    protected array $searchableFields = [];
    protected int $defaultPerPage = 15;
    protected int $maxPerPage = 100;

    public function __construct(mixed $query, array|ServerRequestInterface $request = [])
    {
        $this->query = $query;

        if ($request instanceof ServerRequestInterface) {
            $this->params = $request->getQueryParams();
            if (empty($this->params) && $request->getUri()->getQuery()) {
                parse_str($request->getUri()->getQuery(), $this->params);
            }
        } else {
            $this->params = $request;
        }
    }

    public static function for(mixed $query, array|ServerRequestInterface $request = []): static
    {
        return new static($query, $request);
    }

    public function allowedFilters(array $filters): static
    {
        $this->allowedFilters = $filters;
        return $this;
    }

    public function allowedSorts(array $sorts): static
    {
        $this->allowedSorts = $sorts;
        return $this;
    }

    public function allowedIncludes(array $includes): static
    {
        $this->allowedIncludes = $includes;
        return $this;
    }

    public function searchable(array $fields): static
    {
        $this->searchableFields = $fields;
        return $this;
    }

    public function defaultPerPage(int $perPage): static
    {
        $this->defaultPerPage = $perPage;
        return $this;
    }

    /**
     * Apply all query filters, searching, sorting, includes, and field projections.
     */
    public function apply(): mixed
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySorting();
        $this->applyIncludes();
        $this->applyFields();

        return $this->query;
    }

    /**
     * Apply filters: ?filter[status]=active or ?filter[price][gte]=100
     */
    protected function applyFilters(): void
    {
        $filters = $this->params['filter'] ?? [];
        if (!is_array($filters)) {
            return;
        }

        foreach ($filters as $field => $value) {
            if (!empty($this->allowedFilters) && !in_array($field, $this->allowedFilters, true)) {
                continue;
            }

            if (!is_array($value)) {
                $this->query->where($field, '=', $value);
                continue;
            }

            foreach ($value as $op => $val) {
                switch (strtolower((string) $op)) {
                    case 'gte':
                    case 'ge':
                        $this->query->where($field, '>=', $val);
                        break;
                    case 'lte':
                    case 'le':
                        $this->query->where($field, '<=', $val);
                        break;
                    case 'gt':
                        $this->query->where($field, '>', $val);
                        break;
                    case 'lt':
                        $this->query->where($field, '<', $val);
                        break;
                    case 'neq':
                    case 'not':
                    case 'ne':
                        $this->query->where($field, '!=', $val);
                        break;
                    case 'like':
                        $this->query->where($field, 'LIKE', $val);
                        break;
                    case 'in':
                        $items = is_array($val) ? $val : explode(',', (string) $val);
                        $this->query->whereIn($field, array_map('trim', $items));
                        break;
                    case 'not_in':
                    case 'notin':
                        $items = is_array($val) ? $val : explode(',', (string) $val);
                        $this->query->whereNotIn($field, array_map('trim', $items));
                        break;
                    case 'null':
                        if (filter_var($val, FILTER_VALIDATE_BOOLEAN)) {
                            $this->query->whereNull($field);
                        } else {
                            $this->query->whereNotNull($field);
                        }
                        break;
                    case 'not_null':
                    case 'notnull':
                        $this->query->whereNotNull($field);
                        break;
                    case 'between':
                        $bounds = is_array($val) ? $val : explode(',', (string) $val);
                        if (count($bounds) === 2) {
                            $this->query->whereBetween($field, [trim($bounds[0]), trim($bounds[1])]);
                        }
                        break;
                    default:
                        $this->query->where($field, '=', $val);
                        break;
                }
            }
        }
    }

    /**
     * Apply search across searchable columns: ?search=keyword or ?q=keyword
     */
    protected function applySearch(): void
    {
        $term = $this->params['search'] ?? ($this->params['q'] ?? null);
        if ($term === null || $term === '') {
            return;
        }

        $fields = $this->searchableFields;

        // If query model defines $searchable, use that
        if (empty($fields) && method_exists($this->query, 'getModel')) {
            $model = $this->query->getModel();
            if (isset($model->searchable) && is_array($model->searchable)) {
                $fields = $model->searchable;
            }
        }

        if (empty($fields)) {
            $fields = ['title', 'name', 'email', 'description'];
        }

        $pattern = '%' . str_replace('%', '\%', $term) . '%';

        if (method_exists($this->query, 'whereNested')) {
            $this->query->whereNested(function ($sub) use ($fields, $pattern) {
                foreach ($fields as $i => $field) {
                    if ($i === 0) {
                        $sub->where($field, 'LIKE', $pattern);
                    } else {
                        $sub->orWhere($field, 'LIKE', $pattern);
                    }
                }
            });
        } else {
            foreach ($fields as $field) {
                $this->query->orWhere($field, 'LIKE', $pattern);
            }
        }
    }

    /**
     * Apply sorting: ?sort=-created_at,price or ?sort_by=price&sort_dir=desc
     */
    protected function applySorting(): void
    {
        $sortParam = $this->params['sort'] ?? null;

        if ($sortParam) {
            $sortFields = explode(',', $sortParam);
            foreach ($sortFields as $field) {
                $field = trim($field);
                if (empty($field)) {
                    continue;
                }

                $direction = str_starts_with($field, '-') ? 'DESC' : 'ASC';
                $cleanField = ltrim($field, '-+');

                if (!empty($this->allowedSorts) && !in_array($cleanField, $this->allowedSorts, true)) {
                    continue;
                }

                $this->query->orderBy($cleanField, $direction);
            }
            return;
        }

        if (isset($this->params['sort_by'])) {
            $by = $this->params['sort_by'];
            $dir = strtoupper($this->params['sort_dir'] ?? 'ASC');
            if (empty($this->allowedSorts) || in_array($by, $this->allowedSorts, true)) {
                $this->query->orderBy($by, $dir === 'DESC' ? 'DESC' : 'ASC');
            }
        }
    }

    /**
     * Apply eager loading includes: ?include=category,reviews
     */
    protected function applyIncludes(): void
    {
        $includeParam = $this->params['include'] ?? null;
        if (!$includeParam || !method_exists($this->query, 'with')) {
            return;
        }

        $includes = array_map('trim', explode(',', $includeParam));
        $validIncludes = [];

        foreach ($includes as $relation) {
            if (!empty($this->allowedIncludes) && !in_array($relation, $this->allowedIncludes, true)) {
                continue;
            }
            $validIncludes[] = $relation;
        }

        if (!empty($validIncludes)) {
            $this->query->with(...$validIncludes);
        }
    }

    /**
     * Apply field projections: ?fields=id,title,price
     */
    protected function applyFields(): void
    {
        $fieldsParam = $this->params['fields'] ?? null;
        if (!$fieldsParam) {
            return;
        }

        $fields = array_map('trim', explode(',', $fieldsParam));
        if (!empty($fields)) {
            $this->query->select($fields);
        }
    }

    /**
     * Paginate the query results and return standardized payload.
     *
     * @return array{data: array, meta: array}
     */
    public function paginate(?int $perPage = null, ?int $page = null): array
    {
        $this->apply();

        $page = $page ?? max(1, (int) ($this->params['page'] ?? 1));
        $perPage = $perPage ?? (int) ($this->params['per_page'] ?? ($this->params['limit'] ?? $this->defaultPerPage));
        $perPage = min($this->maxPerPage, max(1, $perPage));

        $countQuery = clone $this->query;
        $total = $countQuery->count();

        $offset = ($page - 1) * $perPage;
        $records = $this->query->offset($offset)->limit($perPage)->get();

        $data = $records instanceof \Switch\Foundation\Collection\Enumerable
            ? $records->toArray()
            : (is_array($records) ? $records : []);

        $lastPage = (int) ceil($total / $perPage);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, $lastPage),
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
                'has_more' => $page < $lastPage,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

readonly class ModelResourceQuery
{
    /**
     * @template T of Model
     * @param class-string<T> $model
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: list<string>}> $constraints
     * @param list<ColumnSchema> $schema
     * @return array<string, mixed>
     */
    public static function paginate(string $model, array $constraints, array $schema, Request $request): array
    {
        $query = $model::query()->withoutEagerLoads();

        foreach ($constraints as ['param' => $param, 'fk' => $fk, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
            if (!in_array($required, $perms)) {
                throw new AccessDeniedHttpException();
            }
            $parentId = self::routeInt($request, $param);
            self::findOrThrow($parentModel, $parentId);
            $query->where($fk, $parentId);
        }

        // ?search=value — filter across non-datetime columns (OR) using the same operator
        // inference as per-column filters: %foo% → LIKE, >100 → >, etc.
        // Datetime columns are always excluded; columns that can't parse the value are skipped.
        $search = $request->query('search');
        if (is_string($search) && $search !== '') {
            $query->where(function ($q) use ($schema, $search) {
                foreach ($schema as $column) {
                    if ($column->type === 'datetime') {
                        continue;
                    }
                    try {
                        [$operator, $value] = self::parseFilter($column, $search);
                    } catch (BadRequestHttpException) {
                        continue;
                    }
                    if ($operator === 'between') {
                        /** @var array<int, mixed> $value */
                        $q->orWhereBetween($column->name, $value);
                    } else {
                        $q->orWhere($column->name, $operator, $value);
                    }
                }
            });
        }

        // ?prop=value — filter on a specific column; operator is inferred from the value prefix/content
        foreach ($schema as $column) {
            $raw = $request->query($column->name);
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            [$operator, $value] = self::parseFilter($column, $raw);
            if ($operator === 'between') {
                /** @var array<int, mixed> $value */
                $query->whereBetween($column->name, $value);
            } else {
                $query->where($column->name, $operator, $value);
            }
        }

        /** @var array<string, mixed> */
        return $query->paginate(10)->toArray();
    }

    /**
     * Parses a raw query-string value into an [operator, value] pair.
     *
     * Numeric/datetime columns support:
     *   >100   → ['>', 100]       (greater than)
     *   <100   → ['<', 100]       (less than)
     *   >=100  → ['>=', 100]      (greater than or equal)
     *   <=100  → ['<=', 100]      (less than or equal)
     *   10/20  → ['between', [10, 20]]
     *
     * Text and enum columns support:
     *   %foo%  → ['like', '%foo%']  (any value containing %)
     *
     * Everything else falls back to ['=', parsedValue].
     *
     * @return array{0: string, 1: mixed}
     */
    public static function parseFilter(ColumnSchema $column, string $raw): array
    {
        $isNumericOrDatetime = in_array($column->type, ['integer', 'number', 'datetime']);
        // enum is included: ?currency=E% matches backed string values via LIKE;
        // ?currency=EUR falls through to = and the parser does the enum cast
        $isText = !in_array($column->type, ['integer', 'number', 'datetime']);

        // Wrap all parser calls so invalid values (e.g. unknown enum cases, bad dates)
        // surface as 400 Bad Request rather than a 500 ValueError/Exception
        $parse = function (string $value) use ($column): mixed {
            try {
                return ($column->parser)($value);
            } catch (Throwable) {
                throw new BadRequestHttpException("Invalid value for column `{$column->name}`");
            }
        };

        if ($isNumericOrDatetime) {
            if (str_contains($raw, '%')) {
                throw new BadRequestHttpException("Column `{$column->name}` does not support LIKE operators");
            }
            if (str_contains($raw, '/')) {
                [$from, $to] = explode('/', $raw, 2);

                return ['between', [$parse($from), $parse($to)]];
            }
            if (str_starts_with($raw, '>=')) {
                return ['>=', $parse(substr($raw, 2))];
            }
            if (str_starts_with($raw, '<=')) {
                return ['<=', $parse(substr($raw, 2))];
            }
            if (str_starts_with($raw, '>')) {
                return ['>', $parse(substr($raw, 1))];
            }
            if (str_starts_with($raw, '<')) {
                return ['<', $parse(substr($raw, 1))];
            }
        }

        if ($isText && str_contains($raw, '%')) {
            return ['like', $raw];
        }

        return ['=', $parse($raw)];
    }

    /**
     * @template T of Model
     * @param class-string<T> $model
     * @return T
     */
    public static function findOrThrow(string $model, int $resourceId): Model
    {
        return $model::query()->findOr($resourceId, fn() => throw new ResourceNotFoundException($resourceId));
    }

    /**
     * Safely extracts an integer route parameter, returning 0 if absent or non-string.
     */
    private static function routeInt(Request $request, string $param): int
    {
        $value = $request->route($param);

        return is_string($value) ? (int) $value : 0;
    }
}

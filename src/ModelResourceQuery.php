<?php

namespace Tcds\Io\Prince;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class ModelResourceQuery
{
    /**
     * @template T of Model
     * @param class-string<T> $model
     * @param array<string, ColumnSchema> $schema
     * @return mixed
     */
    public static function paginate(string $model, array $constraints, array $schema, Request $request)
    {
        $query = $model::query()->withoutEagerLoads();

        foreach ($constraints as ['param' => $param, 'fk' => $fk, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
            if (!in_array($required, $perms)) {
                throw new AccessDeniedHttpException();
            }
            $parentId = (int) $request->route($param);
            self::findOrThrow($parentModel, $parentId);
            $query->where($fk, $parentId);
        }

        // ?search=foo — equal match across all columns (OR), LIKE and other operators coming later
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($schema, $search) {
                foreach ($schema as $column) {
                    $q->orWhere($column->name, $search);
                }
            });
        }

        // ?prop=value — filter on a specific column; operator is inferred from the value prefix/content
        foreach ($schema as $column) {
            $raw = $request->query($column->name);
            if ($raw === null || $raw === '') {
                continue;
            }
            [$operator, $value] = self::parseFilter($column, $raw);
            if ($operator === 'between') {
                $query->whereBetween($column->name, $value);
            } else {
                $query->where($column->name, $operator, $value);
            }
        }

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
     * Text columns support:
     *   %foo%  → ['like', '%foo%']  (any value containing %)
     *
     * Everything else falls back to ['=', parsedValue].
     *
     * @return array{0: string, 1: mixed}
     */
    private static function parseFilter(ColumnSchema $column, string $raw): array
    {
        $isNumericOrDatetime = in_array($column->type, ['integer', 'number', 'datetime']);
        $isText = !in_array($column->type, ['integer', 'number', 'datetime', 'enum']);

        if ($isNumericOrDatetime) {
            if (str_contains($raw, '/')) {
                [$from, $to] = explode('/', $raw, 2);
                return ['between', [($column->parser)($from), ($column->parser)($to)]];
            }
            if (str_starts_with($raw, '>=')) {
                return ['>=', ($column->parser)(substr($raw, 2))];
            }
            if (str_starts_with($raw, '<=')) {
                return ['<=', ($column->parser)(substr($raw, 2))];
            }
            if (str_starts_with($raw, '>')) {
                return ['>', ($column->parser)(substr($raw, 1))];
            }
            if (str_starts_with($raw, '<')) {
                return ['<', ($column->parser)(substr($raw, 1))];
            }
        }

        if ($isText && str_contains($raw, '%')) {
            return ['like', $raw];
        }

        return ['=', ($column->parser)($raw)];
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
}

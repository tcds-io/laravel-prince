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

        // ?prop=foo — exact equal match on a specific column, value is parsed via the column's type
        foreach ($schema as $column) {
            $value = $request->query($column->name);
            if ($value !== null && $value !== '') {
                $query->where($column->name, ($column->parser)($value));
            }
        }

        return $query->paginate(10)->toArray();
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

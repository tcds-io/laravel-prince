<?php

namespace Tcds\Io\Prince;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

readonly class ModelResource
{
    /**
     * @param class-string<Model> $model
     * @param list<string> $userPermissions
     * @param array{ list: string, get: string, create: string, update: string, delete: string } $actionPermissions
     */
    public static function of(
        string $model,
        array $userPermissions = [
            'model:list',
            'model:get',
            'model:create',
            'model:update',
            'model:delete',
        ],
        array $actionPermissions = [
            'list' => 'model:list',
            'get' => 'model:get',
            'create' => 'model:create',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ],
        array $resources = [],
    ): void {
        $reflection = new ReflectionClass($model);
        $table = $reflection->getProperty('table')->getDefaultValue();
        $casts = $reflection->getProperty('casts')->getDefaultValue();

        Route::prefix($table)->group(function () use ($model, $table, $casts, $userPermissions, $actionPermissions) {
            $schema = self::schema($table, $casts);

            self::list($model, $table, $schema)->middleware(ResourceMiddleware::of($actionPermissions['list'], $userPermissions));
            self::get($model, $schema)->middleware(ResourceMiddleware::of($actionPermissions['get'], $userPermissions));
            self::create($model, $schema)->middleware(ResourceMiddleware::of($actionPermissions['create'], $userPermissions));
            self::update($model, $schema)->middleware(ResourceMiddleware::of($actionPermissions['update'], $userPermissions));
            self::delete($model)->middleware(ResourceMiddleware::of($actionPermissions['delete'], $userPermissions));
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, ColumnSchema> $schema
     */
    private static function list(string $model, string $table, array $schema): RouteInstance
    {
        return Route::get('/', function () use ($model, $table, $schema) {
            $paginate = $model::query()
                ->withoutEagerLoads()
                ->paginate(10)
                ->toArray();

            return response()->json([
                'data' => $paginate['data'],
                'meta' => [
                    'resource' => $table,
                    'schema' => $schema,
                    'current_page' => $paginate['current_page'],
                    'from' => $paginate['from'],
                    'to' => $paginate['to'],
                    'total' => $paginate['total'],
                    'last_page' => $paginate['last_page'],
                    'per_page' => $paginate['per_page'],
                ],
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, ColumnSchema> $schema
     */
    private static function get(string $model, array $schema): RouteInstance
    {
        return Route::get("/{resourceId}", function (int $resourceId) use ($model, $schema) {
            $record = self::findOrThrow($model, $resourceId);

            return response()->json([
                'data' => $record,
                'meta' => [
                    'resource' => $record->getTable(),
                    'schema' => $schema,
                ],
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, ColumnSchema> $schema
     */
    private static function create(string $model, array $schema): RouteInstance
    {
        return Route::post("/", function (Request $request) use ($model, $schema) {
            $data = self::data($schema, $request);

            try {
                /** @var object{ id: int } $record */
                $record = $model::query()->create($data);
            } catch (QueryException $exception) {
                [, , $error] = $exception->errorInfo;
                throw new BadRequestHttpException($error ?? "Failed to create resource");
            }

            return response()->json([
                'id' => $record->id,
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<string, ColumnSchema> $schema
     */
    public static function update(string $model, array $schema): RouteInstance
    {
        return Route::patch("/{resourceId}", function (int $resourceId, Request $request) use ($model, $schema) {
            $record = self::findOrThrow($model, $resourceId);
            $data = array_filter(self::data($schema, $request));
            $record->update($data);

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    public static function delete(string $model): RouteInstance
    {
        return Route::delete("/{resourceId}", function (int $resourceId) use ($model) {
            $record = self::findOrThrow($model, $resourceId);
            $record->delete();

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @template T of Model
     * @param class-string<T> $model
     * @return T
     */
    private static function findOrThrow(string $model, int $resourceId): Model
    {
        return $model::query()->findOr($resourceId, fn() => throw new ResourceNotFoundException($resourceId));
    }

    /**
     * @param array<string, ColumnSchema> $schema
     * @return array<string, mixed>
     */
    private static function data(array $schema, Request $request): array
    {
        return collect($schema)
            ->mapWithKeys(function (ColumnSchema $column) use ($request) {
                try {
                    return [$column->name => $column->valueOf($request)];
                } catch (Throwable) {
                    throw new BadRequestHttpException("Value of `$column->name` is invalid");
                }
            })
            ->toArray();
    }

    /**
     * @return array<string, ColumnSchema>
     */
    private static function schema($table, $casts): array
    {
        return collect(Schema::getColumns($table))
            ->map(function ($item) use ($casts) {
                $name = $item['name'];
                $type = $casts[$name] ?? $item['type_name'];
                $column = self::columnSchemaOf($type);

                return new ColumnSchema(
                    name: $name,
                    type: $column['type'],
                    parser: $column['parser'],
                    values: $column['values'] ?? null,
                );
            })
            ->toArray();
    }

    /**
     * @return array{ type: string, parser: Closure, values?: list<string> }
     */
    private static function columnSchemaOf(string $type): array
    {
        return match (true) {
            enum_exists($type) => [
                'type' => 'enum',
                ...self::enumColumnSchemaOf($type),
            ],
            in_array($type, ['bigint', 'integer']) => [
                'type' => 'integer',
                'parser' => fn($value) => (int) $value,
            ],
            $type === 'datetime' => [
                'type' => 'datetime',
                'parser' => fn($value) => new Carbon($value),
            ],
            $type === 'immutable_datetime' => [
                'type' => 'datetime',
                'parser' => fn($value) => new CarbonImmutable($value),
            ],
            in_array($type, ['decimal', 'float']) => [
                'type' => 'number',
                'parser' => fn($value) => (float) $value,
            ],
            default => [
                'type' => $type,
                'parser' => fn($value) => $value,
            ],
        };
    }

    /**
     * @param class-string<BackedEnum> $type
     * @return array
     */
    private static function enumColumnSchemaOf(string $type): array
    {
        return [
            'parser' => fn($value) => $type::from($value),
            'values' => array_map(fn(BackedEnum $v) => $v->value, $type::cases()),
        ];
    }
}

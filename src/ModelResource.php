<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use BackedEnum;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

readonly class ModelResource
{
    /**
     * @param class-string<Model> $model
     * @param list<string> $userPermissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $actionPermissions
     * @param array<int|string, ModelResource> $resources
     */
    private function __construct(
        private string $model,
        private array $userPermissions,
        private array $actionPermissions,
        private array $resources,
        private ?string $fragment,
    ) {}

    /**
     * Builds a ModelResource definition. Call ->routes() on the result to register routes.
     *
     * @param class-string<Model> $model
     * @param list<string> $userPermissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $actionPermissions
     * @param array<int|string, ModelResource|class-string<Model>> $resources
     * @param string|null $fragment Custom URL segment (defaults to the model's table name)
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
        ?string $fragment = null,
    ): self {
        $normalizedResources = array_map(function (ModelResource|string $resource): ModelResource {
            return is_string($resource) ? self::of($resource) : $resource;
        }, $resources);

        return new self($model, $userPermissions, $actionPermissions, $normalizedResources, $fragment);
    }

    /**
     * Registers all routes for this resource and any nested resources recursively.
     */
    public function routes(): void
    {
        $table = $this->table();

        Route::prefix($this->routePrefix())->group(function () use ($table) {
            $this->registerInGroup($table, []);
        });
    }

    private function routePrefix(): string
    {
        return $this->fragment ?? $this->table();
    }

    /**
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: list<string>}> $constraints
     */
    private function registerInGroup(string $table, array $constraints): void
    {
        $schema = self::schema($table, $this->casts());
        /** @var list<string> $nestedResourceNames */
        $nestedResourceNames = array_values(array_map(fn(ModelResource $r) => $r->routePrefix(), $this->resources));

        // /_schema must be registered before /{resourceId} to avoid being captured as an ID
        self::schemaRoute($table, $schema, $nestedResourceNames)->middleware((string) ResourceMiddleware::of($this->actionPermissions['list'], $this->userPermissions));
        self::list($this->model, $table, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->actionPermissions['list'], $this->userPermissions));
        self::get($this->model, $schema, $constraints, $nestedResourceNames)->middleware((string) ResourceMiddleware::of($this->actionPermissions['get'], $this->userPermissions));
        self::create($this->model, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->actionPermissions['create'], $this->userPermissions));
        self::update($this->model, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->actionPermissions['update'], $this->userPermissions));
        self::delete($this->model, $constraints)->middleware((string) ResourceMiddleware::of($this->actionPermissions['delete'], $this->userPermissions));

        foreach ($this->resources as $foreignKey => $nestedResource) {
            if (is_int($foreignKey)) {
                $foreignKey = Str::singular($table) . '_id';
            }

            $parentParam = Str::singular($table) . 'Id';
            $nestedConstraints = [...$constraints, [
                'param' => $parentParam,
                'fk' => $foreignKey,
                'model' => $this->model,
                'requiredPermission' => $this->actionPermissions['get'],
                'userPermissions' => $this->userPermissions,
            ]];
            $nestedTable = $nestedResource->table();

            Route::prefix('{' . $parentParam . '}/' . $nestedResource->routePrefix())->group(function () use ($nestedResource, $nestedTable, $nestedConstraints) {
                $nestedResource->registerInGroup($nestedTable, $nestedConstraints);
            });
        }
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: list<string>}> $constraints
     */
    private static function list(string $model, string $table, array $schema, array $constraints): RouteInstance
    {
        return Route::get('/', function (Request $request) use ($model, $table, $schema, $constraints) {
            $paginate = ModelResourceQuery::paginate($model, $constraints, $schema, $request);

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
     * @param list<ColumnSchema> $schema
     * @param list<string> $nestedResourceNames
     */
    private static function schemaRoute(string $table, array $schema, array $nestedResourceNames): RouteInstance
    {
        return Route::get('/_schema', function () use ($table, $schema, $nestedResourceNames) {
            return response()->json([
                'resource' => $table,
                'resources' => $nestedResourceNames,
                'schema' => $schema,
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: list<string>}> $constraints
     * @param list<string> $nestedResourceNames
     */
    private static function get(string $model, array $schema, array $constraints, array $nestedResourceNames): RouteInstance
    {
        return Route::get('/{resourceId}', function (Request $request) use ($model, $schema, $constraints, $nestedResourceNames) {
            $resourceId = self::routeInt($request, 'resourceId');
            $query = $model::query();

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, $perms)) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeInt($request, $param));
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }

            return response()->json([
                'data' => $record,
                'meta' => [
                    'resource' => $record->getTable(),
                    'schema' => $schema,
                    'resources' => $nestedResourceNames,
                ],
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: list<string>}> $constraints
     */
    private static function create(string $model, array $schema, array $constraints): RouteInstance
    {
        return Route::post('/', function (Request $request) use ($model, $schema, $constraints) {
            $data = self::data($schema, $request);

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, $perms)) {
                    throw new AccessDeniedHttpException();
                }
                $parentId = self::routeInt($request, $param);
                ModelResourceQuery::findOrThrow($parentModel, $parentId);
                $data[$fk] = $parentId;
            }

            try {
                /** @var object{ id: int } $record */
                $record = $model::query()->create($data);
            } catch (QueryException $exception) {
                $errorInfo = $exception->errorInfo;
                $error = is_array($errorInfo) ? ($errorInfo[2] ?? null) : null;

                throw new BadRequestHttpException(is_string($error) ? $error : 'Failed to create resource');
            }

            return response()->json([
                'id' => $record->id,
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: list<string>}> $constraints
     */
    private static function update(string $model, array $schema, array $constraints): RouteInstance
    {
        return Route::patch('/{resourceId}', function (Request $request) use ($model, $schema, $constraints) {
            $resourceId = self::routeInt($request, 'resourceId');
            $query = $model::query();
            $foreignKeys = [];

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, $perms)) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeInt($request, $param));
                $foreignKeys[] = $fk;
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }

            $data = array_filter(self::data($schema, $request));

            foreach ($foreignKeys as $fk) {
                unset($data[$fk]);
            }

            $record->update($data);

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: list<string>}> $constraints
     */
    private static function delete(string $model, array $constraints): RouteInstance
    {
        return Route::delete('/{resourceId}', function (Request $request) use ($model, $constraints) {
            $resourceId = self::routeInt($request, 'resourceId');
            $query = $model::query();

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, $perms)) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeInt($request, $param));
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }
            $record->delete();

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    private function table(): string
    {
        $value = (new ReflectionClass($this->model))->getProperty('table')->getDefaultValue();

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function casts(): array
    {
        $value = (new ReflectionClass($this->model))->getProperty('casts')->getDefaultValue();

        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param list<ColumnSchema> $schema
     * @return array<string, mixed>
     */
    private static function data(array $schema, Request $request): array
    {
        $result = [];

        foreach ($schema as $column) {
            try {
                $result[$column->name] = $column->valueOf($request);
            } catch (Throwable) {
                throw new BadRequestHttpException("Value of `$column->name` is invalid");
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $casts
     * @return list<ColumnSchema>
     */
    private static function schema(string $table, array $casts): array
    {
        $result = [];

        foreach (Schema::getColumns($table) as $item) {
            /** @var array{name: string, type_name: string} $item */
            $name = $item['name'];
            $castType = $casts[$name] ?? null;
            $type = is_string($castType) ? $castType : $item['type_name'];
            $column = self::columnSchemaOf($type);

            $result[] = new ColumnSchema(
                name: $name,
                type: $column['type'],
                parser: $column['parser'],
                values: $column['values'] ?? null,
            );
        }

        return $result;
    }

    /**
     * @return array{type: string, parser: Closure, values?: list<string>}
     */
    private static function columnSchemaOf(string $type): array
    {
        return match (true) {
            enum_exists($type) && is_a($type, BackedEnum::class, true) => [
                'type' => 'enum',
                ...self::enumColumnSchemaOf($type),
            ],
            in_array($type, ['bigint', 'integer']) => [
                'type' => 'integer',
                'parser' => fn(string $value) => (int) $value,
            ],
            $type === 'datetime' => [
                'type' => 'datetime',
                'parser' => fn(string $value) => new Carbon($value),
            ],
            $type === 'immutable_datetime' => [
                'type' => 'datetime',
                'parser' => fn(string $value) => new CarbonImmutable($value),
            ],
            in_array($type, ['decimal', 'float']) => [
                'type' => 'number',
                'parser' => fn(string $value) => (float) $value,
            ],
            default => [
                'type' => $type,
                'parser' => fn(mixed $value): mixed => $value,
            ],
        };
    }

    /**
     * @param class-string<BackedEnum> $type
     * @return array{parser: Closure, values: list<string>}
     */
    private static function enumColumnSchemaOf(string $type): array
    {
        return [
            'parser' => fn(int|string $value) => $type::from($value),
            'values' => array_map(fn(BackedEnum $v) => (string) $v->value, $type::cases()),
        ];
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

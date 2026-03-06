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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

readonly class ModelResource
{
    /**
     * @param class-string<Model> $model
     * @param Closure(): list<string> $userPermissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $resourcePermissions
     * @param array<int|string, ModelResource> $resources
     */
    private function __construct(
        private string $model,
        private Closure $userPermissions,
        private array $resourcePermissions,
        private array $resources,
        private ?string $fragment,
        public bool $globalSearch = false,
    ) {}

    /**
     * Builds a ModelResource definition. Call ->routes() on the result to register routes.
     *
     * @param class-string<Model> $model
     * @param (Closure(): list<string>)|null $userPermissions Invoked per request — defaults to granting all standard model permissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $resourcePermissions Maps each action to the permission string it requires
     * @param array<int|string, ModelResource|class-string<Model>> $resources
     * @param string|null $fragment Custom URL segment (defaults to the model's table name)
     * @param bool $globalSearch Whether this resource is included in global search
     */
    public static function of(
        string $model,
        ?Closure $userPermissions = null,
        array $resourcePermissions = [
            'list' => 'model:list',
            'get' => 'model:get',
            'create' => 'model:create',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ],
        array $resources = [],
        ?string $fragment = null,
        bool $globalSearch = false,
    ): self {
        $userPermissions ??= fn() => ['model:list', 'model:get', 'model:create', 'model:update', 'model:delete'];

        $normalizedResources = array_map(function (ModelResource|string $resource): ModelResource {
            return is_string($resource) ? self::of($resource) : $resource;
        }, $resources);

        return new self($model, $userPermissions, $resourcePermissions, $normalizedResources, $fragment, $globalSearch);
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

    /**
     * Returns the metadata needed for global search indexing of this resource.
     * Called by ModelResourceBuilder to collect entries before registering routes.
     *
     * @return array{table: string, routePrefix: string, schema: list<ColumnSchema>}
     */
    public function searchEntry(): array
    {
        $table = $this->table();

        return [
            'table' => $table,
            'routePrefix' => $this->routePrefix(),
            'schema' => self::schema($table, $this->casts()),
        ];
    }

    private function routePrefix(): string
    {
        return $this->fragment ?? $this->table();
    }

    /**
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private function registerInGroup(string $table, array $constraints): void
    {
        $schema = self::schema($table, $this->casts());

        // Collect nested resource info in one pass: used both for the GET inner lists
        // and for registering nested route groups below.
        // PHP allows accessing private members of other instances of the same class.
        /** @var list<array{routePrefix: string, model: class-string<Model>, foreignKey: string}> $nestedEntries */
        $nestedEntries = [];

        foreach ($this->resources as $foreignKey => $nestedResource) {
            $nestedEntries[] = [
                'routePrefix' => $nestedResource->routePrefix(),
                'model' => $nestedResource->model,
                'foreignKey' => is_int($foreignKey) ? Str::singular($table) . '_id' : $foreignKey,
            ];
        }

        $nestedResourceNames = array_column($nestedEntries, 'routePrefix');

        // /_schema must be registered before /{resourceId} to avoid being captured as an ID
        self::schemaRoute($table, $schema, $nestedResourceNames)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['list'], $this->userPermissions));
        self::list($this->model, $table, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['list'], $this->userPermissions));
        self::get($this->model, $schema, $constraints, $nestedEntries)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['get'], $this->userPermissions));
        self::create($this->model, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['create'], $this->userPermissions));
        self::update($this->model, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['update'], $this->userPermissions));
        self::delete($this->model, $constraints)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['delete'], $this->userPermissions));

        foreach ($this->resources as $foreignKey => $nestedResource) {
            if (is_int($foreignKey)) {
                $foreignKey = Str::singular($table) . '_id';
            }

            $parentParam = Str::singular($table) . 'Id';
            $nestedConstraints = [...$constraints, [
                'param' => $parentParam,
                'fk' => $foreignKey,
                'model' => $this->model,
                'requiredPermission' => $this->resourcePermissions['get'],
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
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private static function list(string $model, string $table, array $schema, array $constraints): RouteInstance
    {
        return Route::get('/', function (Request $request) use ($model, $table, $schema, $constraints) {
            $paginate = ModelResourceQuery::paginate($model, $constraints, $schema, $request);

            $basePath = rtrim($request->getPathInfo(), '/');
            /** @var list<array<string, mixed>> $rawData */
            $rawData = $paginate['data'];
            $data = array_map(
                fn(array $item) => [...$item, '_resource' => $basePath . '/' . $item['id']],
                $rawData,
            );

            return response()->json([
                'data' => $data,
                'meta' => [
                    'resource' => $table,
                    'schema' => $schema,
                    'current_page' => $paginate['current_page'],
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
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     * @param list<array{routePrefix: string, model: class-string<Model>, foreignKey: string}> $nestedEntries
     */
    private static function get(string $model, array $schema, array $constraints, array $nestedEntries): RouteInstance
    {
        return Route::get('/{resourceId}', function (Request $request) use ($model, $schema, $constraints, $nestedEntries) {
            $resourceId = self::routeId($request, 'resourceId');
            $query = $model::query()->withoutEagerLoads();

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, ($perms)())) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeId($request, $param));
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }

            $basePath = $request->getPathInfo();
            $data = $record->toArray();

            foreach ($nestedEntries as ['routePrefix' => $routePrefix, 'model' => $nestedModel, 'foreignKey' => $foreignKey]) {
                /** @var list<array<string, mixed>> $nestedItems */
                $nestedItems = $nestedModel::query()
                    ->withoutEagerLoads()
                    ->where($foreignKey, $resourceId)
                    ->get()
                    ->toArray();

                $nestedBasePath = $basePath . '/' . $routePrefix;
                $data[$routePrefix] = array_map(
                    fn(array $item) => [...$item, '_resource' => $nestedBasePath . '/' . $item['id']],
                    $nestedItems,
                );
            }

            return response()->json([
                'data' => $data,
                'meta' => [
                    'resource' => $record->getTable(),
                    'schema' => $schema,
                    'resources' => array_column($nestedEntries, 'routePrefix'),
                ],
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private static function create(string $model, array $schema, array $constraints): RouteInstance
    {
        return Route::post('/', function (Request $request) use ($model, $schema, $constraints) {
            $data = self::data($schema, $request);

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, ($perms)())) {
                    throw new AccessDeniedHttpException();
                }
                $parentId = self::routeId($request, $param);
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
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private static function update(string $model, array $schema, array $constraints): RouteInstance
    {
        return Route::patch('/{resourceId}', function (Request $request) use ($model, $schema, $constraints) {
            $resourceId = self::routeId($request, 'resourceId');
            $query = $model::query();
            $foreignKeys = [];

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, ($perms)())) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeId($request, $param));
                $foreignKeys[] = $fk;
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }

            $data = array_filter(self::data($schema, $request), fn(string $key) => $request->has($key), ARRAY_FILTER_USE_KEY);

            foreach ($foreignKeys as $fk) {
                unset($data[$fk]);
            }

            $record->update($data);

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private static function delete(string $model, array $constraints): RouteInstance
    {
        return Route::delete('/{resourceId}', function (Request $request) use ($model, $constraints) {
            $resourceId = self::routeId($request, 'resourceId');
            $query = $model::query();

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, ($perms)())) {
                    throw new AccessDeniedHttpException();
                }
                $query->where($fk, self::routeId($request, $param));
            }

            $record = $query->find($resourceId);
            if ($record === null) {
                throw new ResourceNotFoundException($resourceId);
            }
            $record->delete();

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    private function instance(): Model
    {
        $model = $this->model;

        return new $model();
    }

    private function table(): string
    {
        return $this->instance()->getTable();
    }

    /**
     * @return array<string, mixed>
     */
    private function casts(): array
    {
        /** @var array<string, mixed> */
        return $this->instance()->getCasts();
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
            in_array($type, ['bigint', 'integer', 'int']) => [
                'type' => 'integer',
                'parser' => fn(string $value) => (int) $value,
            ],
            in_array($type, ['text', 'varchar', 'char']) => [
                'type' => 'text',
                'parser' => fn(string $value) => $value,
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
     * Extracts a route parameter as int or string.
     * Returns an int for numeric values (e.g. auto-increment IDs) and a string otherwise
     * (e.g. UUIDs). Falls back to 0 only when the parameter is absent — which indicates a
     * routing misconfiguration rather than a normal request.
     */
    private static function routeId(Request $request, string $param): int|string
    {
        $value = $request->route($param);
        if (!is_string($value)) {
            return 0;
        }

        return is_numeric($value) ? (int) $value : $value;
    }
}

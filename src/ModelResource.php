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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tcds\Io\Prince\Events\MutableDataEvent;
use Tcds\Io\Prince\Events\ResourceCreated;
use Tcds\Io\Prince\Events\ResourceCreating;
use Tcds\Io\Prince\Events\ResourceDeleted;
use Tcds\Io\Prince\Events\ResourceDeleting;
use Tcds\Io\Prince\Events\ResourceUpdated;
use Tcds\Io\Prince\Events\ResourceUpdating;
use Throwable;

readonly class ModelResource
{
    /**
     * @param class-string<Model> $model
     * @param Closure(): list<string> $userPermissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $resourcePermissions
     * @param array<int|string, ModelResource> $resources
     * @param list<ResourceAction> $actions
     * @param array<string, class-string> $events
     */
    private function __construct(
        private string $model,
        private Closure $userPermissions,
        private array $resourcePermissions,
        private array $resources,
        private ?string $segment,
        public bool $globalSearch = false,
        public bool $belongsTo = false,
        public bool $embed = false,
        private array $actions = [],
        private array $events = [],
    ) {}

    /**
     * Builds a ModelResource definition. Call ->routes() on the result to register routes.
     *
     * @param class-string<Model> $model
     * @param (Closure(): list<string>)|null $userPermissions Invoked per request — defaults to granting all standard model permissions
     * @param array{list: string, get: string, create: string, update: string, delete: string} $resourcePermissions Maps each action to the permission string it requires
     * @param array<int|string, ModelResource|class-string<Model>> $resources
     * @param string|null $segment Custom URL segment (defaults to the model's table name)
     * @param bool $globalSearch Whether this resource is included in global search
     * @param list<ResourceAction> $actions Extra routes attached to this resource
     * @param array<string, class-string> $events Lifecycle event overrides — merged with defaults (creating, created, updating, updated, deleting, deleted)
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
        ?string $segment = null,
        bool $globalSearch = false,
        bool $belongsTo = false,
        bool $embed = false,
        array $actions = [],
        array $events = [],
    ): self {
        $userPermissions ??= fn() => ['model:list', 'model:get', 'model:create', 'model:update', 'model:delete'];

        $normalizedResources = array_map(function (ModelResource|string $resource): ModelResource {
            return is_string($resource) ? self::of($resource) : $resource;
        }, $resources);

        $resolvedEvents = array_merge([
            'creating' => ResourceCreating::class,
            'created'  => ResourceCreated::class,
            'updating' => ResourceUpdating::class,
            'updated'  => ResourceUpdated::class,
            'deleting' => ResourceDeleting::class,
            'deleted'  => ResourceDeleted::class,
        ], $events);

        return new self($model, $userPermissions, $resourcePermissions, $normalizedResources, $segment, $globalSearch, $belongsTo, $embed, $actions, $resolvedEvents);
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
        return $this->segment ?? $this->table();
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
        /** @var list<array{routePrefix: string, embedKey: string, model: class-string<Model>, foreignKey: string, belongsTo: bool, embed: bool}> $nestedEntries */
        $nestedEntries = [];

        foreach ($this->resources as $foreignKey => $nestedResource) {
            if ($nestedResource->belongsTo) {
                // FK (column) lives on the parent record; infer it from the child's table name.
                $column = is_int($foreignKey) ? Str::singular($nestedResource->routePrefix()) . '_id' : $foreignKey;
            } else {
                // FK lives on the child record; infer it from the parent's table name.
                $column = is_int($foreignKey) ? Str::singular($table) . '_id' : $foreignKey;
            }

            $nestedEntries[] = [
                'routePrefix' => $nestedResource->routePrefix(),
                'embedKey' => $nestedResource->belongsTo ? Str::singular($nestedResource->routePrefix()) : $nestedResource->routePrefix(),
                'model' => $nestedResource->model,
                'foreignKey' => $column,
                'belongsTo' => $nestedResource->belongsTo,
                'embed' => $nestedResource->embed,
            ];
        }

        $nestedResourceNames = array_column($nestedEntries, 'routePrefix');

        // /_schema must be registered before /{resourceId} to avoid being captured as an ID.
        // Collection actions (no {id} in path) must also precede /{resourceId} for the same reason.
        self::schemaRoute($table, $schema, $nestedResourceNames)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['list'], $this->userPermissions));

        foreach ($this->actions as $action) {
            if (!$action->isItemAction()) {
                $this->registerAction($action, $constraints);
            }
        }

        self::list($this->model, $table, $schema, $constraints)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['list'], $this->userPermissions));
        self::get($this->model, $constraints, $nestedEntries)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['get'], $this->userPermissions));
        self::create($this->model, $schema, $constraints, $this->events)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['create'], $this->userPermissions));
        self::update($this->model, $schema, $constraints, $this->events)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['update'], $this->userPermissions));
        self::delete($this->model, $constraints, $this->events)->middleware((string) ResourceMiddleware::of($this->resourcePermissions['delete'], $this->userPermissions));

        foreach ($this->actions as $action) {
            if ($action->isItemAction()) {
                $this->registerAction($action, $constraints);
            }
        }

        foreach ($this->resources as $foreignKey => $nestedResource) {
            if ($nestedResource->belongsTo) {
                // FK lives on the parent; register a scoped GET /{parentId}/{prefix}/{nestedId} route.
                $column = is_int($foreignKey) ? Str::singular($nestedResource->routePrefix()) . '_id' : $foreignKey;
                $parentParam = Str::singular($table) . 'Id';
                $nestedParam = Str::singular($nestedResource->routePrefix()) . 'Id';
                $parentModel = $this->model;
                $parentRequiredPermission = $this->resourcePermissions['get'];
                $parentUserPermissions = $this->userPermissions;

                Route::get(
                    '{' . $parentParam . '}/' . $nestedResource->routePrefix() . '/{' . $nestedParam . '}',
                    function (Request $request) use ($nestedResource, $column, $parentParam, $nestedParam, $constraints, $parentModel, $parentRequiredPermission, $parentUserPermissions) {
                        // Check outer constraints (grandparent permissions, etc.).
                        foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                            if (!in_array($required, ($perms)())) {
                                throw new AccessDeniedHttpException();
                            }
                        }

                        // Check parent's own get permission.
                        if (!in_array($parentRequiredPermission, ($parentUserPermissions)())) {
                            throw new AccessDeniedHttpException();
                        }

                        $parentId = self::routeId($request, $parentParam);
                        $nestedId = self::routeId($request, $nestedParam);

                        // Validate parent exists.
                        $parent = $parentModel::query()->withoutEagerLoads()->find($parentId);
                        if ($parent === null) {
                            throw new ResourceNotFoundException($parentId);
                        }

                        // Validate the parent's FK points to the requested nested resource.
                        $fkValue = $parent->{$column} ?? null;
                        if (!is_scalar($fkValue) || (string) $fkValue !== (string) $nestedId) {
                            throw new ResourceNotFoundException($nestedId);
                        }

                        $nested = $nestedResource->model::query()->withoutEagerLoads()->find($nestedId);
                        if ($nested === null) {
                            throw new ResourceNotFoundException($nestedId);
                        }

                        $basePath = $request->getPathInfo();

                        return response()->json([
                            'data' => [...$nested->toArray(), '_resource' => $basePath],
                        ]);
                    }
                )->middleware((string) ResourceMiddleware::of($nestedResource->resourcePermissions['get'], $nestedResource->userPermissions));

                continue;
            }

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
            /** @var list<array{id: int|string, ...}> $rawData */
            $rawData = $paginate['data'];
            $data = array_map(
                fn(array $item) => [...$item, '_resource' => $basePath . '/' . (string) $item['id']],
                $rawData,
            );

            return response()->json([
                'data' => $data,
                'meta' => [
                    'resource' => $table,
                    'current_page' => $paginate['current_page'],
                    'total' => $paginate['total'],
                    'last_page' => $paginate['last_page'],
                    'per_page' => $paginate['per_page'],
                    'prev_page' => $paginate['prev_page_url'],
                    'next_page' => $paginate['next_page_url'],
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
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     * @param list<array{routePrefix: string, embedKey: string, model: class-string<Model>, foreignKey: string, belongsTo: bool, embed: bool}> $nestedEntries
     */
    private static function get(string $model, array $constraints, array $nestedEntries): RouteInstance
    {
        return Route::get('/{resourceId}', function (Request $request) use ($model, $constraints, $nestedEntries) {
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

            // Build navigational links for every nested resource regardless of embed setting.
            // Keys use the singular embedKey; paths use the plural routePrefix.
            // For belongsTo the FK value is known from the parent record, so the full path is included.
            /** @var array<string, string|null> $resourceLinks */
            $resourceLinks = [];
            foreach ($nestedEntries as $entry) {
                if ($entry['belongsTo']) {
                    $fkValue = $data[$entry['foreignKey']] ?? null;
                    $resourceLinks[$entry['embedKey']] = $fkValue !== null
                        ? $basePath . '/' . $entry['routePrefix'] . '/' . (is_scalar($fkValue) ? (string) $fkValue : '')
                        : null;
                } else {
                    $resourceLinks[$entry['embedKey']] = $basePath . '/' . $entry['routePrefix'];
                }
            }

            foreach ($nestedEntries as ['routePrefix' => $routePrefix, 'embedKey' => $embedKey, 'model' => $nestedModel, 'foreignKey' => $foreignKey, 'belongsTo' => $isBelongsTo, 'embed' => $shouldEmbed]) {
                if (!$shouldEmbed) {
                    continue;
                }

                $nestedBasePath = $basePath . '/' . $routePrefix;

                if ($isBelongsTo) {
                    // FK is on the parent record; resolve the single related object.
                    $fkValue = $data[$foreignKey] ?? null;
                    /** @var array{id: int|string, ...}|null $nestedItem */
                    $nestedItem = $fkValue !== null
                        ? $nestedModel::query()->withoutEagerLoads()->find($fkValue)?->toArray()
                        : null;
                    $data[$embedKey] = $nestedItem !== null
                        ? [...$nestedItem, '_resource' => $nestedBasePath . '/' . (string) $nestedItem['id']]
                        : null;
                } else {
                    /** @var list<array{id: int|string, ...}> $nestedItems */
                    $nestedItems = $nestedModel::query()
                        ->withoutEagerLoads()
                        ->where($foreignKey, $resourceId)
                        ->get()
                        ->toArray();

                    $data[$embedKey] = array_map(
                        fn(array $item) => [...$item, '_resource' => $nestedBasePath . '/' . (string) $item['id']],
                        $nestedItems,
                    );
                }
            }

            return response()->json([
                'data' => $data,
                'meta' => [
                    'resource' => $record->getTable(),
                    'resources' => $resourceLinks,
                ],
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     * @param array<string, class-string> $events
     */
    private static function create(string $model, array $schema, array $constraints, array $events): RouteInstance
    {
        return Route::post('/', function (Request $request) use ($model, $schema, $constraints, $events) {
            $data = self::data($schema, $request);

            foreach ($constraints as ['param' => $param, 'fk' => $fk, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                if (!in_array($required, ($perms)())) {
                    throw new AccessDeniedHttpException();
                }
                $parentId = self::routeId($request, $param);
                ModelResourceQuery::findOrThrow($parentModel, $parentId);
                $data[$fk] = $parentId;
            }

            $creatingEvent = new $events['creating']($model, $data);
            Event::dispatch($creatingEvent);
            if ($creatingEvent instanceof MutableDataEvent) {
                $data = $creatingEvent->data;
            }

            try {
                /** @var object{ id: int } $record */
                $record = $model::query()->create($data);
            } catch (QueryException $exception) {
                $errorInfo = $exception->errorInfo;
                $error = is_array($errorInfo) ? ($errorInfo[2] ?? null) : null;

                throw new BadRequestHttpException(is_string($error) ? $error : 'Failed to create resource');
            }

            Event::dispatch(new $events['created']($record));

            return response()->json([
                'id' => $record->id,
            ]);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param list<ColumnSchema> $schema
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     * @param array<string, class-string> $events
     */
    private static function update(string $model, array $schema, array $constraints, array $events): RouteInstance
    {
        return Route::patch('/{resourceId}', function (Request $request) use ($model, $schema, $constraints, $events) {
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

            $updatingEvent = new $events['updating']($record, $data);
            Event::dispatch($updatingEvent);
            if ($updatingEvent instanceof MutableDataEvent) {
                $data = $updatingEvent->data;
            }

            $record->update($data);

            Event::dispatch(new $events['updated']($record));

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * @param class-string<Model> $model
     * @param array<array{param: string, fk: string, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     * @param array<string, class-string> $events
     */
    private static function delete(string $model, array $constraints, array $events): RouteInstance
    {
        return Route::delete('/{resourceId}', function (Request $request) use ($model, $constraints, $events) {
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

            Event::dispatch(new $events['deleting']($record));

            $record->delete();

            Event::dispatch(new $events['deleted']($resourceId));

            return response(status: Response::HTTP_NO_CONTENT);
        });
    }

    /**
     * Registers a single custom action route within the current route group.
     *
     * Item actions (path contains {id}): the matching record is resolved from the DB and injected
     * into the callback via the IoC container. Returns 404 if not found.
     * Collection actions: any parent constraints are validated; the callback receives no implicit
     * arguments beyond what the IoC container can inject (Request, services, etc.).
     *
     * @param array<array{param: string, fk: string, model: class-string<Model>, requiredPermission: string, userPermissions: (Closure(): list<string>)}> $constraints
     */
    private function registerAction(ResourceAction $action, array $constraints): void
    {
        $model = $this->model;
        $userPermissions = $this->userPermissions;

        if ($action->isItemAction()) {
            $closure = function (Request $request) use ($model, $action, $constraints) {
                $id = self::routeId($request, 'id');
                $query = $model::query();

                foreach ($constraints as ['param' => $param, 'fk' => $fk, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                    if (!in_array($required, ($perms)())) {
                        throw new AccessDeniedHttpException();
                    }
                    $query->where($fk, self::routeId($request, $param));
                }

                $record = $query->find($id);
                if ($record === null) {
                    throw new ResourceNotFoundException($id);
                }

                return app()->call($action->action, [$model => $record]);
            };
        } else {
            $closure = function (Request $request) use ($action, $constraints) {
                foreach ($constraints as ['param' => $param, 'model' => $parentModel, 'requiredPermission' => $required, 'userPermissions' => $perms]) {
                    if (!in_array($required, ($perms)())) {
                        throw new AccessDeniedHttpException();
                    }
                    ModelResourceQuery::findOrThrow($parentModel, self::routeId($request, $param));
                }

                return app()->call($action->action);
            };
        }

        $route = match ($action->method) {
            'GET'    => Route::get($action->path, $closure),
            'POST'   => Route::post($action->path, $closure),
            'PUT'    => Route::put($action->path, $closure),
            'PATCH'  => Route::patch($action->path, $closure),
            'DELETE' => Route::delete($action->path, $closure),
            default  => throw new \LogicException("Unsupported HTTP method: {$action->method}"),
        };

        if ($action->permission !== null) {
            $route->middleware((string) ResourceMiddleware::of($action->permission, $userPermissions));
        }
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

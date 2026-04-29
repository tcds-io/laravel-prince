<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-type Permission string
 * Fluent builder for registering one or more ModelResource routes with a shared authorizer.
 * Eliminates the need to repeat authorizer on every resource and manages global search
 * entries internally — no static state, no variable capture required.
 *
 * Usage:
 *   ModelResourceBuilder::create()
 *       ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions()))
 *       ->resource(Invoice::class, resources: fn($b) => $b->resource(InvoiceItem::class), globalSearch: true)
 *       ->resource(Order::class,   resources: fn($b) => $b->resource(OrderItem::class),   globalSearch: true)
 *       ->routes();
 */
final class ModelResourceBuilder
{
    /** @var list<array{resource: ModelResource, foreignKey: ?string}> */
    private array $entries = [];

    /** @var list<array{table: string, routePrefix: string, connection: string|null, schema: Closure(): list<ColumnSchema>}> */
    private array $searchEntries = [];

    /** @var Closure */
    private Closure $authorizer;

    private int $maxLimit;

    private function __construct(int $maxLimit = 100)
    {
        $this->maxLimit = $maxLimit;
        $this->authorizer = fn() => true;
    }

    public static function create(int $maxLimit = 100): self
    {
        return new self($maxLimit);
    }

    /**
     * Sets the authorizer closure inherited by all resources in this builder.
     * All parameters are resolved via the IoC container. Declare `RequestContext`
     * as a parameter to receive the current request's method, path, and permission string.
     *
     * @param Closure $authorizer Returns true to allow, false to deny (403)
     */
    public function authorizer(Closure $authorizer): self
    {
        $this->authorizer = $authorizer;

        return $this;
    }

    /**
     * Adds a resource to the builder.
     *
     * @param class-string<Model> $model
     * @param (Closure(self): void)|null $resources Callback to define nested resources; the nested builder inherits the same authorizer
     * @param bool $globalSearch Whether to include this resource in the global /search route
     * @param string|null $segment Custom URL segment (defaults to the model's table name)
     * @param string|null $foreignKey FK column referencing the parent table (only meaningful when used inside a $resources callback; defaults to {singular_parent_table}_id)
     * @param array{read?: Permission, create?: Permission, update?: Permission, delete?: Permission} $permissions Maps each action to the permission string it requires
     * @param list<ResourceAction> $actions Extra routes attached to this resource
     * @param array<string, class-string> $events Lifecycle event overrides — merged with defaults (creating, created, updating, updated, deleting, deleted)
     * @param int|null $maxLimit Maximum page size for this resource — overrides the builder default when set
     */
    public function resource(
        string $model,
        ?Closure $resources = null,
        bool $globalSearch = false,
        ?string $segment = null,
        ?string $foreignKey = null,
        array $permissions = [
            'read' => 'public',
            'create' => 'public',
            'update' => 'public',
            'delete' => 'public',
        ],
        array $actions = [],
        array $events = [],
        ?int $maxLimit = null,
    ): self {
        return $this->addResource(model: $model, resources: $resources, globalSearch: $globalSearch, segment: $segment, foreignKey: $foreignKey, permissions: $permissions, actions: $actions, events: $events, maxLimit: $maxLimit);
    }

    /**
     * Registers a has-many nested resource (FK on the child model, embeds as an array).
     * Use inside a $resources callback. Equivalent to resource() with explicit has-many semantics.
     *
     * @param class-string<Model> $model
     * @param string|null $foreignKey FK column on the child model (defaults to {singular_parent_table}_id)
     * @param array{read?: Permission, create?: Permission, update?: Permission, delete?: Permission} $permissions
     * @param list<ResourceAction> $actions
     * @param array<string, class-string> $events
     * @param int|null $maxLimit Maximum page size for this resource — overrides the builder default when set
     */
    public function hasMany(
        string $model,
        ?string $foreignKey = null,
        ?string $segment = null,
        bool $embed = false,
        array $permissions = [
            'read' => 'public',
            'create' => 'public',
            'update' => 'public',
            'delete' => 'public',
        ],
        array $actions = [],
        array $events = [],
        ?int $maxLimit = null,
    ): self {
        return $this->addResource(model: $model, segment: $segment, foreignKey: $foreignKey, embed: $embed, permissions: $permissions, actions: $actions, events: $events, belongsTo: false, maxLimit: $maxLimit);
    }

    /**
     * Registers a belongs-to nested resource (FK on the parent model, embeds as a single object or null).
     * Use inside a $resources callback. No nested CRUD routes are registered — the related model
     * is accessible via its own top-level resource.
     *
     * @param class-string<Model> $model
     * @param string|null $column Column on the parent model holding the FK (defaults to {singular_related_table}_id)
     * @param array{read?: Permission, create?: Permission, update?: Permission, delete?: Permission} $permissions
     * @param int|null $maxLimit Maximum page size for this resource — overrides the builder default when set
     */
    public function belongsTo(
        string $model,
        ?string $column = null,
        ?string $segment = null,
        bool $embed = false,
        array $permissions = [
            'read' => 'public',
            'create' => 'public',
            'update' => 'public',
            'delete' => 'public',
        ],
        ?int $maxLimit = null,
    ): self {
        return $this->addResource(model: $model, segment: $segment, foreignKey: $column, embed: $embed, permissions: $permissions, belongsTo: true, maxLimit: $maxLimit);
    }

    /**
     * @param class-string<Model> $model
     * @param (Closure(self): void)|null $resources
     * @param array{read?: Permission, create?: Permission, update?: Permission, delete?: Permission} $permissions
     * @param list<ResourceAction> $actions
     * @param array<string, class-string> $events
     * @param int|null $maxLimit Resource-specific override; falls back to the builder default when null
     */
    private function addResource(
        string $model,
        ?Closure $resources = null,
        bool $globalSearch = false,
        ?string $segment = null,
        ?string $foreignKey = null,
        bool $embed = false,
        array $permissions = [
            'read' => 'public',
            'create' => 'public',
            'update' => 'public',
            'delete' => 'public',
        ],
        array $actions = [],
        array $events = [],
        bool $belongsTo = false,
        ?int $maxLimit = null,
    ): self {
        $nestedBuilder = (new self($this->maxLimit))->authorizer($this->authorizer);

        if ($resources !== null) {
            $resources($nestedBuilder);
        }

        // Build the resources array for ModelResource::of(), preserving custom FK keys
        $nestedResources = [];

        foreach ($nestedBuilder->entries as ['resource' => $r, 'foreignKey' => $fk]) {
            if ($fk !== null) {
                $nestedResources[$fk] = $r;
            } else {
                $nestedResources[] = $r;
            }
        }

        $resource = ModelResource::of(
            model: $model,
            authorizer: $this->authorizer,
            permissions: $permissions,
            resources: $nestedResources,
            segment: $segment,
            globalSearch: $globalSearch,
            belongsTo: $belongsTo,
            embed: $embed,
            actions: $actions,
            events: $events,
            maxLimit: $maxLimit ?? $this->maxLimit,
        );

        $this->entries[] = ['resource' => $resource, 'foreignKey' => $foreignKey];

        if ($globalSearch) {
            $this->searchEntries[] = $resource->searchEntry();
        }

        // Propagate global search entries from nested resources
        foreach ($nestedBuilder->searchEntries as $entry) {
            $this->searchEntries[] = $entry;
        }

        return $this;
    }

    /**
     * Registers all resource routes, a global GET /_schema, and (if opted in) GET /search.
     * Must be called after all resource() calls.
     */
    public function routes(): void
    {
        $seen = [];
        foreach ($this->entries as ['resource' => $resource]) {
            $prefix = $resource->routePrefix();
            if (array_key_exists($prefix, $seen)) {
                throw new \LogicException(
                    "Two resources share the same route prefix '{$prefix}': {$seen[$prefix]} and {$resource->model}. "
                    . "Use the 'segment' parameter on one of them to assign a unique URL prefix.",
                );
            }
            $seen[$prefix] = $resource->model;
        }

        $schemaEntries = [];

        foreach ($this->entries as ['resource' => $resource]) {
            $resource->routes();
            $schemaEntries[] = $resource->schemaEntry();
        }

        ModelResourceGlobalSchema::of($schemaEntries)->routes();

        if ($this->searchEntries !== []) {
            ModelResourceGlobalSearch::of($this->searchEntries)->routes();
        }
    }
}

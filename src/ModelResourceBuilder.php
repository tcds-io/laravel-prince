<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * @phpstan-type Permission string
 * Fluent builder for registering one or more ModelResource routes with shared user permissions.
 * Eliminates the need to repeat userPermissions on every resource and manages global search
 * entries internally — no static state, no variable capture required.
 *
 * Usage:
 *   ModelResourceBuilder::create(userPermissions: [...])
 *       ->resource(Invoice::class, resources: fn($b) => $b->resource(InvoiceItem::class), globalSearch: true)
 *       ->resource(Order::class,   resources: fn($b) => $b->resource(OrderItem::class),   globalSearch: true)
 *       ->routes();
 */
final class ModelResourceBuilder
{
    /** @var list<array{resource: ModelResource, foreignKey: ?string}> */
    private array $entries = [];

    /** @var list<array{table: string, routePrefix: string, schema: Closure(): list<ColumnSchema>}> */
    private array $searchEntries = [];

    /**
     * @param Closure(): list<Permission> $userPermissions
     */
    private function __construct(private readonly Closure $userPermissions) {}

    /**
     * Creates a new builder. The given userPermissions are inherited by all resources.
     * Each resource sets its own resourcePermissions via resource().
     *
     * @param (Closure(): list<Permission>)|null $userPermissions The permissions the current user holds
     */
    public static function create(
        ?Closure $userPermissions = null,
    ): self {
        return new self($userPermissions ?? fn() => [
            'default-model:list',
            'default-model:get',
            'default-model:create',
            'default-model:update',
            'default-model:delete',
        ]);
    }

    /**
     * Adds a resource to the builder.
     *
     * @param class-string<Model> $model
     * @param (Closure(self): void)|null $resources Callback to define nested resources; the nested builder inherits the same userPermissions
     * @param bool $globalSearch Whether to include this resource in the global /search route
     * @param string|null $segment Custom URL segment (defaults to the model's table name)
     * @param string|null $foreignKey FK column referencing the parent table (only meaningful when used inside a $resources callback; defaults to {singular_parent_table}_id)
     * @param array{list: Permission, get: Permission, create: Permission, update: Permission, delete: Permission} $resourcePermissions Maps each action to the permission string it requires
     * @param list<ResourceAction> $actions Extra routes attached to this resource
     * @param array<string, class-string> $events Lifecycle event overrides — merged with defaults (creating, created, updating, updated, deleting, deleted)
     */
    public function resource(
        string $model,
        ?Closure $resources = null,
        bool $globalSearch = false,
        ?string $segment = null,
        ?string $foreignKey = null,
        array $resourcePermissions = [
            'list' => 'default-model:list',
            'get' => 'default-model:get',
            'create' => 'default-model:create',
            'update' => 'default-model:update',
            'delete' => 'default-model:delete',
        ],
        array $actions = [],
        array $events = [],
    ): self {
        return $this->addResource(model: $model, resources: $resources, globalSearch: $globalSearch, segment: $segment, foreignKey: $foreignKey, resourcePermissions: $resourcePermissions, actions: $actions, events: $events);
    }

    /**
     * Registers a has-many nested resource (FK on the child model, embeds as an array).
     * Use inside a $resources callback. Equivalent to resource() with explicit has-many semantics.
     *
     * @param class-string<Model> $model
     * @param string|null $foreignKey FK column on the child model (defaults to {singular_parent_table}_id)
     * @param array{list: Permission, get: Permission, create: Permission, update: Permission, delete: Permission} $resourcePermissions
     * @param list<ResourceAction> $actions
     * @param array<string, class-string> $events
     */
    public function hasMany(
        string $model,
        ?string $foreignKey = null,
        ?string $segment = null,
        bool $embed = false,
        array $resourcePermissions = [
            'list' => 'default-model:list',
            'get' => 'default-model:get',
            'create' => 'default-model:create',
            'update' => 'default-model:update',
            'delete' => 'default-model:delete',
        ],
        array $actions = [],
        array $events = [],
    ): self {
        return $this->addResource(model: $model, segment: $segment, foreignKey: $foreignKey, embed: $embed, resourcePermissions: $resourcePermissions, actions: $actions, events: $events, belongsTo: false);
    }

    /**
     * Registers a belongs-to nested resource (FK on the parent model, embeds as a single object or null).
     * Use inside a $resources callback. No nested CRUD routes are registered — the related model
     * is accessible via its own top-level resource.
     *
     * @param class-string<Model> $model
     * @param string|null $column Column on the parent model holding the FK (defaults to {singular_related_table}_id)
     * @param array{list: Permission, get: Permission, create: Permission, update: Permission, delete: Permission} $resourcePermissions
     */
    public function belongsTo(
        string $model,
        ?string $column = null,
        ?string $segment = null,
        bool $embed = false,
        array $resourcePermissions = [
            'list' => 'default-model:list',
            'get' => 'default-model:get',
            'create' => 'default-model:create',
            'update' => 'default-model:update',
            'delete' => 'default-model:delete',
        ],
    ): self {
        return $this->addResource(model: $model, segment: $segment, foreignKey: $column, embed: $embed, resourcePermissions: $resourcePermissions, belongsTo: true);
    }

    /**
     * @param class-string<Model> $model
     * @param (Closure(self): void)|null $resources
     * @param array{list: Permission, get: Permission, create: Permission, update: Permission, delete: Permission} $resourcePermissions
     * @param list<ResourceAction> $actions
     * @param array<string, class-string> $events
     */
    private function addResource(
        string $model,
        ?Closure $resources = null,
        bool $globalSearch = false,
        ?string $segment = null,
        ?string $foreignKey = null,
        bool $embed = false,
        array $resourcePermissions = [
            'list' => 'default-model:list',
            'get' => 'default-model:get',
            'create' => 'default-model:create',
            'update' => 'default-model:update',
            'delete' => 'default-model:delete',
        ],
        array $actions = [],
        array $events = [],
        bool $belongsTo = false,
    ): self {
        $nestedBuilder = new self($this->userPermissions);

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
            userPermissions: $this->userPermissions,
            resourcePermissions: $resourcePermissions,
            resources: $nestedResources,
            segment: $segment,
            globalSearch: $globalSearch,
            belongsTo: $belongsTo,
            embed: $embed,
            actions: $actions,
            events: $events,
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
     * Registers all resource routes. If any resources were added with globalSearch: true,
     * also registers GET /search. Must be called after all resource() calls.
     */
    public function routes(): void
    {
        foreach ($this->entries as ['resource' => $resource]) {
            $resource->routes();
        }

        if ($this->searchEntries !== []) {
            ModelResourceGlobalSearch::of($this->searchEntries)->routes();
        }
    }
}

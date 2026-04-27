# Prince! Your Laravel Model API

> "Dearly beloved, we are gathered here today to get through this thing called life."
> — Prince

Turn any Eloquent model into a fully working REST API — no controllers, no form requests, no manual routes.

```php
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(Invoice::class)
    ->resource(Product::class)
    ->routes();
```

---

## What you get

For every registered model the following routes are created automatically, using the model's `$table` as the URL
segment:

| Method   | Path                | Action                                       |
|----------|---------------------|----------------------------------------------|
| `GET`    | `/invoices`         | Paginated list                               |
| `GET`    | `/invoices/_schema` | Column schema, nested resources, permissions |
| `GET`    | `/invoices/{id}`    | Single record (+ embedded nested lists)      |
| `POST`   | `/invoices`         | Create one — or batch-create many            |
| `PATCH`  | `/invoices/{id}`    | Update one                                   |
| `PATCH`  | `/invoices`         | Batch-update many                            |
| `DELETE` | `/invoices/{id}`    | Delete one                                   |
| `DELETE` | `/invoices`         | Batch-delete many                            |

Plus one route at the builder group level:

| Method | Path       | Action                              |
|--------|------------|-------------------------------------|
| `GET`  | `/_schema` | Schema for all registered resources |

And when global search is enabled:

| Method | Path      | Action                                         |
|--------|-----------|------------------------------------------------|
| `GET`  | `/search` | Full-text search across all opted-in resources |

---

## Installation

```bash
composer require tcds-io/laravel-prince
```

No configuration needed — call it directly from your routes file.

---

## Usage

Register your resources inside `routes/api.php`:

```php
use Tcds\Io\Prince\ModelResourceBuilder;
use Tcds\Io\Prince\AuthorizerContext;

Route::prefix('/api/backoffice')->group(function () {
    ModelResourceBuilder::create()
        ->authorizer(fn(AuthorizerContext $ctx) => in_array($ctx->permission, $request->user()->permissions))
        ->resource(Invoice::class, globalSearch: true)
        ->resource(Product::class, globalSearch: true)
        ->routes();
});
```

The package reads each model's `$table` and `$casts` properties to determine route prefixes and column types — nothing
to configure.

---

## Nested resources

Register sub-resources via a callback. Nested routes are scoped to the parent automatically, and the parent's FK is
inferred from the table name (`invoice_id` for `invoices`):

```php
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(
        model: Invoice::class,
        resources: fn(ModelResourceBuilder $b) => $b
            ->resource(InvoiceItem::class),
    )
    ->routes();
```

This registers the full nested route set under `/invoices/{invoiceId}/items`, with every request validated against the
parent's existence.

### GET response includes inner lists

`GET /invoices/{id}` automatically embeds each registered nested resource as an inner list — **`$with` eager loads on
the model are ignored**, keeping the API shape fully controlled by what you register:

```json
{
  "data": {
    "id": 1,
    "title": "November invoice",
    "amount": 299.00,
    "items": [
      {
        "id": 10,
        "description": "Widget A",
        "price": 49.00,
        "_resource": "/invoices/1/items/10"
      },
      {
        "id": 11,
        "description": "Widget B",
        "price": 99.00,
        "_resource": "/invoices/1/items/11"
      }
    ]
  },
  "meta": {
    "resource": "invoices",
    "schema": [
      ...
    ],
    "resources": [
      "items"
    ]
  }
}
```

---

## List responses

Every item in a paginated list includes a `_resource` field — the direct URL to that record, including any outer route
prefix and parent path for nested resources:

```json
{
  "data": [
    {
      "id": 1,
      "title": "Invoice A",
      "_resource": "/api/backoffice/invoices/1"
    },
    {
      "id": 2,
      "title": "Invoice B",
      "_resource": "/api/backoffice/invoices/2"
    }
  ],
  "meta": {
    "resource": "invoices",
    "schema": [
      ...
    ],
    "current_page": 1,
    "per_page": 10,
    "total": 2,
    "last_page": 1
  }
}
```

### Pagination

Control page size with `?limit=N` (default `10`, max `100`):

```
GET /invoices?limit=25
GET /invoices?limit=25&page=2
```

The maximum is configurable — set a builder-level default or override it per resource:

```php
// All resources in this builder cap at 50
ModelResourceBuilder::create(maxLimit: 50)
    ->resource(Invoice::class)
    ->resource(Product::class, maxLimit: 10)  // this one caps at 10
    ->routes();
```

Values above `maxLimit` are silently clamped to it.

---

## Batch operations

All three write endpoints support a batch mode alongside their single-record form. Every batch request is wrapped in a
database transaction — if any record fails, the whole operation is rolled back.

### Batch create

Send a `{"data": [...]}` body (an object with a single `data` array key) to create multiple records in one request.
Returns the IDs of all created records:

```
POST /invoices
{"data": [{"title": "Invoice A", "amount": 100}, {"title": "Invoice B", "amount": 200}]}

→ 200 {"data": [{"id": 1}, {"id": 2}]}
```

A plain object body (no `data` wrapper, or `data` alongside other keys) is treated as a single-record create as usual:

```
POST /invoices
{"title": "Invoice A", "amount": 100}

→ 200 {"id": 1}
```

### Batch update

`PATCH /invoices` with a `{"data": [...]}` body, where each item must include `id` alongside the fields to update.
Returns `204 No Content`. Returns `404` if any ID is not found (and rolls back all changes):

```
PATCH /invoices
{"data": [{"id": 1, "title": "Updated"}, {"id": 2, "amount": 300}]}

→ 204
```

### Batch delete

`DELETE /invoices` with a `{"data": [id, ...]}` body. Returns `204 No Content`. Returns `404` if any ID is not found (
and rolls back all deletions):

```
DELETE /invoices
{"data": [1, 2, 3]}

→ 204
```

---

## Schema

### Per-resource schema

`GET /invoices/_schema` returns the column schema, registered nested resource names, and the permissions the **current
user** holds for that resource. Always accessible regardless of permission settings.

### Global schema

`GET /_schema` (registered at the builder group level) returns the same information for **all registered resources** in
one request:

```json
{
  "data": [
    {
      "resource": "invoices",
      "schema": [
        ...
      ],
      "resources": [
        "items"
      ],
      "permissions": {
        "read": "invoices:read",
        "create": "invoices:write"
      }
    },
    {
      "resource": "products",
      "schema": [
        ...
      ],
      "resources": [],
      "permissions": {
        "read": "products:read"
      }
    }
  ]
}
```

Always accessible regardless of permissions, and like the per-resource schema, only shows permissions the current user
holds.

### Schema response shape

The per-resource `/_schema` response (used directly or as an element in the global `/_schema`) looks like:

```json
{
  "resource": "invoices",
  "schema": [
    {
      "name": "id",
      "type": "integer"
    },
    {
      "name": "title",
      "type": "text"
    },
    {
      "name": "amount",
      "type": "number"
    },
    {
      "name": "created_at",
      "type": "datetime"
    },
    {
      "name": "updated_at",
      "type": "datetime"
    }
  ],
  "resources": [
    "items"
  ],
  "permissions": {
    "read": "invoices:read",
    "create": "invoices:write",
    "update": "invoices:write",
    "delete": "invoices:delete"
  }
}
```

Only permissions the current user actually holds appear in the map — so a read-only user sees only `read`. Endpoints
with `'public'` permission are always included. Extra [action](#actions) permissions appear under slug-formatted keys:

```json
"permissions": {
"read": "invoices:read",
"post-import": "invoices:write",
"get-id-preview": "invoices:read"
}
```

---

## Filtering

### Full-text search

`?search=value` matches against all non-datetime columns using OR. Operators are inferred from the value:

```
GET /invoices?search=acme          → exact match across all text columns
GET /invoices?search=%acme%        → LIKE match
```

### Column filter

`?{column}=value` filters on a specific column. The same operator inference applies:

```
GET /invoices?title=%acme%         → LIKE
GET /invoices?amount=>100          → greater than 100
GET /invoices?amount=<=500         → less than or equal to 500
GET /invoices?amount=100/500       → between 100 and 500
GET /invoices?status=active        → exact match (also works for enums)
```

**Operator reference**

| Value pattern | Operator  | Column types              |
|---------------|-----------|---------------------------|
| `%foo%`       | `LIKE`    | text, enum                |
| `>N`          | `>`       | integer, number, datetime |
| `<N`          | `<`       | integer, number, datetime |
| `>=N`         | `>=`      | integer, number, datetime |
| `<=N`         | `<=`      | integer, number, datetime |
| `from/to`     | `BETWEEN` | integer, number, datetime |
| anything else | `=`       | all                       |

---

## Global search

Opt any resource into cross-resource full-text search with `globalSearch: true`. A single `GET /search?q=value` endpoint
is registered covering all opted-in resources:

```php
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(Invoice::class, globalSearch: true)
    ->resource(Product::class, globalSearch: true)
    ->resource(Customer::class)          // excluded from search
    ->routes();
```

```
GET /search?q=acme
GET /search?q=%acme%    → LIKE
```

```json
{
  "data": [
    {
      "id": 1,
      "description": "Acme Corp",
      "resource": "invoices",
      "link": "/api/backoffice/invoices/1"
    },
    {
      "id": 7,
      "description": "Acme Widget",
      "resource": "products",
      "link": "/api/backoffice/products/7"
    }
  ]
}
```

Each result has `id`, `description` (first matching text column), `resource` (table name), and `link` (full URL
including any outer route prefix). Each record appears at most once per resource even when multiple text columns match.

---

## Permissions

### Authorizer

A closure that returns `true` to allow access or `false` to deny it (403). Evaluated per request — so it runs after
authentication middleware, can read the request, and supports any auth strategy. Shared across all resources in the
builder — set once, applied everywhere.

All parameters are resolved via the Laravel IoC container. Declare `RequestContext` to receive the current request's
`method`, `path`, and `permission` string:

```php
use Tcds\Io\Prince\AuthorizerContext;

ModelResourceBuilder::create()
    ->authorizer(fn(AuthorizerContext $ctx) => in_array($ctx->permission, $request->user()->permissions))
    ->resource(Invoice::class)
    ->resource(Product::class)
    ->routes();
```

`RequestContext` is **optional** — declare it only when you need it. Other injectables (services, etc.) are resolved
from the container as usual:

```php
// Check permission against the authenticated user
fn(RequestContext $ctx) => in_array($ctx->permission, Auth::user()?->permissions ?? [])

// Inject a service — no RequestContext needed
fn(AuthService $auth) => $auth->isAdmin()

// Use both
fn(RequestContext $ctx, AuthService $auth) => $auth->can($ctx->permission)

// Flat allow/deny (e.g. for public read-only endpoints)
fn() => false
```

### Resource permissions

The permission each action _requires_. Defaults to the strings below; override per resource when your app uses different
permission names:

| Action         | Default required permission |
|----------------|-----------------------------|
| `list` + `get` | `default:model.read`        |
| `create`       | `default:model.create`      |
| `update`       | `default:model.update`      |
| `delete`       | `default:model.delete`      |

```php
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, Auth::user()?->permissions ?? []))
    ->resource(
        model: Invoice::class,
        resourcePermissions: [
            'read'   => 'invoices:read',
            'create' => 'invoices:write',
            'update' => 'invoices:write',
            'delete' => 'invoices:delete',
        ],
    )
    ->routes();
```

Besides regular permission strings, one **reserved keyword** controls a special behaviour:

| Keyword    | Behaviour                                                                    |
|------------|------------------------------------------------------------------------------|
| `'public'` | Route is registered **without** permission middleware — anyone can access it |

To disable an endpoint entirely, simply **omit its key** from `resourcePermissions`. The route will not be registered
and the framework returns 404.

> **Reserved word:** `'public'` must not be used as an actual permission name in your application. It is intercepted by
> the library before any user permission check.

```php
// Read-only resource: anyone can list/get, create/update/delete are not registered
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(
        model: Product::class,
        resourcePermissions: [
            'read' => 'public',
        ],
    )
    ->routes();
```

---

## Type inference

The package inspects each database column and applies the right PHP type automatically. `$casts` on your model takes
priority over the raw DB type.

| DB / cast type       | API type   | Parsed as           |
|----------------------|------------|---------------------|
| `bigint`, `integer`  | `integer`  | `(int)`             |
| `decimal`, `float`   | `number`   | `(float)`           |
| `datetime`           | `datetime` | `Carbon`            |
| `immutable_datetime` | `datetime` | `CarbonImmutable`   |
| Any `BackedEnum`     | `enum`     | `MyEnum::from(...)` |
| Anything else        | `text`     | passthrough         |

Enum values are automatically included in the schema:

```json
{
  "name": "status",
  "type": "enum",
  "values": [
    "draft",
    "active",
    "cancelled"
  ]
}
```

---

## Error handling

| Situation                        | HTTP response                       |
|----------------------------------|-------------------------------------|
| Record not found                 | `404 Not Found`                     |
| Missing required permission      | `403 Forbidden`                     |
| Invalid value / constraint error | `400 Bad Request` with error detail |

---

## Actions

Register extra endpoints on a resource with `actions`. Use `ResourceAction::{method}()` — paths containing `{id}` are
item-level (the record is resolved and injected automatically); all other paths are collection-level.

The `action` must be an **invokable class** (a class with `__invoke`). Laravel's IoC container resolves and calls it, so
any type-hinted dependencies are injected automatically.

```php
use Tcds\Io\Prince\AuthorizerContext;
use Tcds\Io\Prince\ResourceAction;

ModelResourceBuilder::create()
    ->authorizer(fn(AuthorizerContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(
        model: Invoice::class,
        resourcePermissions: [
            'read'   => 'invoices:read',
            'create' => 'invoices:write',
            'update' => 'invoices:write',
            'delete' => 'invoices:delete',
        ],
        actions: [
            // Collection-level — POST /invoices/import
            ResourceAction::post(
                path: '/import',
                action: ImportInvoicesAction::class,
                permission: 'invoices:write',
            ),

            // Item-level — POST /invoices/{id}/send
            // The matching Invoice is resolved and injected; returns 404 if not found.
            ResourceAction::post(
                path: '/{id}/send',
                action: SendInvoiceAction::class,
                permission: 'invoices:send',
            ),

            // GET /invoices/{id}/pdf
            ResourceAction::get(
                path: '/{id}/pdf',
                action: InvoicePdfController::class,
                permission: 'invoices:read',
            ),
        ],
    )
    ->routes();
```

**Collection actions** (`/import`) are registered before `/{id}` routes so literal path segments are never captured as
record IDs.

**Item actions** (`/{id}/send`) resolve the record from the database before calling the action. The model instance is
injected by type — any parameter type-hinted with the model class receives it. The full Laravel IoC is available for
additional injectables (`Request`, services, etc.). `permission` is optional; omit it to allow unauthenticated access to
that action.

---

## Events

Every CRUD operation fires a lifecycle event before and after the DB write. Register listeners via standard Laravel
event dispatching — no extra configuration needed.

| Event              | When          | Signature                                |
|--------------------|---------------|------------------------------------------|
| `ResourceCreating` | before insert | `(class-string $modelName, array $data)` |
| `ResourceCreated`  | after insert  | `(Model $model)`                         |
| `ResourceUpdating` | before update | `(Model $model, array $data)`            |
| `ResourceUpdated`  | after update  | `(Model $model)`                         |
| `ResourceDeleting` | before delete | `(Model $model)`                         |
| `ResourceDeleted`  | after delete  | `(int\|string $modelId)`                 |

```php
use Tcds\Io\Prince\Events\ResourceCreated;
use Tcds\Io\Prince\Events\ResourceCreating;

// Side effect — send notification after create
Event::listen(ResourceCreated::class, function (ResourceCreated $event): void {
    if ($event->model instanceof Invoice) {
        Notification::send($event->model->user, new InvoiceCreatedNotification($event->model));
    }
});

// Data mutation — slugify title before save
Event::listen(ResourceCreating::class, function (ResourceCreating $event): void {
    if (isset($event->data['title'])) {
        $event->data['slug'] = Str::slug($event->data['title']);
    }
});
```

`ResourceCreating` and `ResourceUpdating` implement `MutableDataEvent` — any changes to `$event->data` are applied to
the actual DB write.

### Overriding default events

Override any event per resource by passing an `events` array keyed by lifecycle name. Unspecified keys keep their
defaults:

```php
use Tcds\Io\Prince\Events\ResourceCreating;

ModelResource::of(
    model: Invoice::class,
    events: [
        'creating' => InvoiceCreating::class, // replaces ResourceCreating
        'created'  => InvoiceCreated::class,  // replaces ResourceCreated
    ],
);
```

The custom event must expose a public mutable `$data` property to participate in data mutation:

```php
use Tcds\Io\Prince\Events\MutableDataEvent;

class InvoiceCreating implements MutableDataEvent
{
    public function __construct(
        public readonly string $modelName,
        public array $data,
    ) {}
}
```

---

## Custom URL segment

Override the URL segment with `segment` when you want a different path than the table name:

```php
ModelResourceBuilder::create()
    ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions))
    ->resource(model: Invoice::class, segment: 'bills')
    ->routes();
// Routes registered at /bills/... — table name remains invoices in meta/schema
```

---

## Requirements

- PHP `^8.4`
- Laravel `^12.0`

# Prince! Your Laravel Model API

> "Dearly beloved, we are gathered here today to get through this thing called life."
> — Prince

Turn any Eloquent model into a fully working REST API — no controllers, no form requests, no manual routes.

```php
ModelResourceBuilder::create(userPermissions: fn() => $user->permissions)
    ->resource(Invoice::class)
    ->resource(Product::class)
    ->routes();
```

---

## What you get

For every registered model the following routes are created automatically, using the model's `$table` as the URL segment:

| Method   | Path                  | Action                              |
|----------|-----------------------|-------------------------------------|
| `GET`    | `/invoices`           | Paginated list                      |
| `GET`    | `/invoices/_schema`   | Column schema + nested resource names |
| `GET`    | `/invoices/{id}`      | Single record (+ embedded nested lists) |
| `POST`   | `/invoices`           | Create                              |
| `PATCH`  | `/invoices/{id}`      | Update                              |
| `DELETE` | `/invoices/{id}`      | Delete                              |

And when global search is enabled:

| Method | Path      | Action                                                   |
|--------|-----------|----------------------------------------------------------|
| `GET`  | `/search` | Full-text search across all opted-in resources           |

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

Route::prefix('/api/backoffice')->group(function () {
    ModelResourceBuilder::create(userPermissions: fn() => $request->user()->permissions)
        ->resource(Invoice::class, globalSearch: true)
        ->resource(Product::class, globalSearch: true)
        ->routes();
});
```

The package reads each model's `$table` and `$casts` properties to determine route prefixes and column types — nothing to configure.

---

## Nested resources

Register sub-resources via a callback. Nested routes are scoped to the parent automatically, and the parent's FK is inferred from the table name (`invoice_id` for `invoices`):

```php
ModelResourceBuilder::create(userPermissions: fn() => $user->permissions)
    ->resource(
        model: Invoice::class,
        resources: fn(ModelResourceBuilder $b) => $b
            ->resource(InvoiceItem::class),
    )
    ->routes();
```

This registers the full nested route set under `/invoices/{invoiceId}/items`, with every request validated against the parent's existence.

### GET response includes inner lists

`GET /invoices/{id}` automatically embeds each registered nested resource as an inner list — **`$with` eager loads on the model are ignored**, keeping the API shape fully controlled by what you register:

```json
{
  "data": {
    "id": 1,
    "title": "November invoice",
    "amount": 299.00,
    "items": [
      { "id": 10, "description": "Widget A", "price": 49.00, "_resource": "/invoices/1/items/10" },
      { "id": 11, "description": "Widget B", "price": 99.00, "_resource": "/invoices/1/items/11" }
    ]
  },
  "meta": {
    "resource": "invoices",
    "schema": [...],
    "resources": ["items"]
  }
}
```

---

## List responses

Every item in a paginated list includes a `_resource` field — the direct URL to that record, including any outer route prefix and parent path for nested resources:

```json
{
  "data": [
    { "id": 1, "title": "Invoice A", "_resource": "/api/backoffice/invoices/1" },
    { "id": 2, "title": "Invoice B", "_resource": "/api/backoffice/invoices/2" }
  ],
  "meta": {
    "resource": "invoices",
    "schema": [...],
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

| Value pattern  | Operator            | Column types            |
|----------------|---------------------|-------------------------|
| `%foo%`        | `LIKE`              | text, enum              |
| `>N`           | `>`                 | integer, number, datetime |
| `<N`           | `<`                 | integer, number, datetime |
| `>=N`          | `>=`                | integer, number, datetime |
| `<=N`          | `<=`                | integer, number, datetime |
| `from/to`      | `BETWEEN`           | integer, number, datetime |
| anything else  | `=`                 | all                     |

---

## Global search

Opt any resource into cross-resource full-text search with `globalSearch: true`. A single `GET /search?q=value` endpoint is registered covering all opted-in resources:

```php
ModelResourceBuilder::create(userPermissions: fn() => $user->permissions)
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
    { "id": 1, "description": "Acme Corp", "resource": "invoices", "link": "/api/backoffice/invoices/1" },
    { "id": 7, "description": "Acme Widget", "resource": "products", "link": "/api/backoffice/products/7" }
  ]
}
```

Each result has `id`, `description` (first matching text column), `resource` (table name), and `link` (full URL including any outer route prefix). Each record appears at most once per resource even when multiple text columns match.

---

## Permissions

### User permissions

A closure returning the permissions the current user holds. Evaluated per request — so it runs after authentication middleware, can read the request, and supports any auth strategy. Shared across all resources in the builder — pass once, applied everywhere:

```php
ModelResourceBuilder::create(userPermissions: fn() => $request->user()->permissions)
    ->resource(Invoice::class)
    ->resource(Product::class)
    ->routes();
```

Because it's a closure, you can use any source:

```php
// From the authenticated user
fn() => Auth::user()?->permissions ?? []

// From a gate/policy check
fn() => Gate::allows('admin') ? ['invoices:read', 'invoices:write'] : []

// Hard-coded (e.g. for public read-only endpoints)
fn() => ['invoices:read']
```

### Resource permissions

The permission string each action _requires_. Defaults to the strings below; override per resource when your app uses different permission names:

| Action   | Default required permission    |
|----------|--------------------------------|
| `list`   | `default-model:list`           |
| `get`    | `default-model:get`            |
| `create` | `default-model:create`         |
| `update` | `default-model:update`         |
| `delete` | `default-model:delete`         |

```php
ModelResourceBuilder::create(userPermissions: fn() => Auth::user()?->permissions ?? [])
    ->resource(
        model: Invoice::class,
        resourcePermissions: [
            'list'   => 'invoices:read',
            'get'    => 'invoices:read',
            'create' => 'invoices:write',
            'update' => 'invoices:write',
            'delete' => 'invoices:delete',
        ],
    )
    ->routes();
```

---

## Type inference

The package inspects each database column and applies the right PHP type automatically. `$casts` on your model takes priority over the raw DB type.

| DB / cast type        | API type   | Parsed as              |
|-----------------------|------------|------------------------|
| `bigint`, `integer`   | `integer`  | `(int)`                |
| `decimal`, `float`    | `number`   | `(float)`              |
| `datetime`            | `datetime` | `Carbon`               |
| `immutable_datetime`  | `datetime` | `CarbonImmutable`      |
| Any `BackedEnum`      | `enum`     | `MyEnum::from(...)`    |
| Anything else         | `text`     | passthrough            |

Enum values are automatically included in the schema:

```json
{ "name": "status", "type": "enum", "values": ["draft", "active", "cancelled"] }
```

---

## Error handling

| Situation                        | HTTP response                          |
|----------------------------------|----------------------------------------|
| Record not found                 | `404 Not Found`                        |
| Missing required permission      | `403 Forbidden`                        |
| Invalid value / constraint error | `400 Bad Request` with error detail    |

---

## Actions

Register extra endpoints on a resource with `actions`. Use `ResourceAction::{method}()` — paths containing `{id}` are item-level (the record is resolved and injected automatically); all other paths are collection-level.

```php
use Tcds\Io\Prince\ResourceAction;

ModelResourceBuilder::create(userPermissions: fn() => $user->permissions)
    ->resource(
        model: Invoice::class,
        resourcePermissions: [
            'list'   => 'invoices:read',
            'get'    => 'invoices:read',
            'create' => 'invoices:write',
            'update' => 'invoices:write',
            'delete' => 'invoices:delete',
        ],
        actions: [
            // Collection-level — POST /invoices/import
            ResourceAction::post(
                path: '/import',
                action: fn(Request $request) => ImportInvoicesAction::run($request),
                permission: 'invoices:write',
            ),

            // Item-level — POST /invoices/{id}/send
            // The matching Invoice is resolved and injected; returns 404 if not found.
            ResourceAction::post(
                path: '/{id}/send',
                action: fn(Invoice $invoice) => SendInvoiceAction::run($invoice),
                permission: 'invoices:send',
            ),

            // Invokable controller — GET /invoices/{id}/pdf
            ResourceAction::get(
                path: '/{id}/pdf',
                action: InvoicePdfController::class,
                permission: 'invoices:read',
            ),
        ],
    )
    ->routes();
```

**Collection actions** (`/import`) are registered before `/{id}` routes so literal path segments are never captured as record IDs.

**Item actions** (`/{id}/send`) resolve the record from the database before calling the action. The model instance is injected by type — any parameter type-hinted with the model class receives it. The full Laravel IoC is available for additional injectables (`Request`, services, etc.). `permission` is optional; omit it to allow unauthenticated access to that action.

---

## Custom URL segment

Override the URL segment with `segment` when you want a different path than the table name:

```php
ModelResourceBuilder::create(userPermissions: fn() => $user->permissions)
    ->resource(model: Invoice::class, segment: 'bills')
    ->routes();
// Routes registered at /bills/... — table name remains invoices in meta/schema
```

---

## Requirements

- PHP `^8.4`
- Laravel `^12.0`

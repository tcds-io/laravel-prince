# Laravel Model API

Turn any Eloquent model into a fully working REST API with a single line of code.

```php
ModelResource::of(Invoice::class);
```

That's it. No controllers. No form requests. No manual routes.

---

## What you get

Calling `ModelResource::of(Invoice::class)` on a model with `$table = 'invoices'` instantly registers:

| Method   | Path               | Action             |
|----------|--------------------|--------------------|
| `GET`    | `/invoices`        | Paginated list     |
| `GET`    | `/invoices/{id}`   | Single record      |
| `POST`   | `/invoices`        | Create             |
| `PATCH`  | `/invoices/{id}`   | Update             |
| `DELETE` | `/invoices/{id}`   | Delete             |

Every response includes a `meta.schema` block so clients always know the exact shape of the resource — no separate documentation needed.

```json
{
  "data": { "id": 1, "status": "active", "amount": 149.99 },
  "meta": {
    "resource": "invoices",
    "schema": [
      { "name": "id",     "type": "integer" },
      { "name": "status", "type": "enum", "values": ["draft", "active", "cancelled"] },
      { "name": "amount", "type": "number" }
    ]
  }
}
```

---

## Installation

```bash
composer require tcds-io/laravel-prince
```

Laravel auto-discovers the package. No extra configuration needed.

---

## Usage

Register your model resources inside `routes/api.php` (or anywhere your routes are loaded):

```php
use Tcds\Io\Prince\ModelResource;

Route::prefix('/api')
    ->group(function () {
         ModelResource::of(Invoice::class);
         ModelResource::of(Product::class);
         ModelResource::of(Customer::class);
    });
```

The package reads the model's `$table` and `$casts` properties to determine the route prefix and column types automatically.

---

## Type inference

The package inspects each database column and applies the right PHP type automatically. The `$casts` property on your model takes priority over the raw DB type.

| DB / cast type        | API type   | Parsed as              |
|-----------------------|------------|------------------------|
| `bigint`, `integer`   | `integer`  | `(int)`                |
| `decimal`, `float`    | `number`   | `(float)`              |
| `datetime`            | `datetime` | `Carbon`               |
| `immutable_datetime`  | `datetime` | `CarbonImmutable`      |
| Any `BackedEnum`      | `enum`     | `MyEnum::from(...)`    |
| Anything else         | raw string | passthrough            |

For enums, the valid values are automatically included in the schema response:

```json
{ "name": "status", "type": "enum", "values": ["draft", "active", "cancelled"] }
```

---

## Permissions

Every route is protected by a permission guard out of the box. By default the package has the following permission strings to be present for the request to proceed:

| Action   | Required permission |
|----------|---------------------|
| `list`   | `model:list`        |
| `get`    | `model:get`         |
| `create` | `model:create`      |
| `update` | `model:update`      |
| `delete` | `model:delete`      |

Pass the user's current permissions as the second argument:

```php
ModelResource::of(
    model: Invoice::class,
    userPermissions: $request->user()->permissions,
);
```

### Custom permission names

Override the required permission for any action via the third argument:

```php
ModelResource::of(
    model: Invoice::class,
    userPermissions: $request->user()->permissions,
    actionPermissions: [
        'list'   => 'invoices:read',
        'get'    => 'invoices:read',
        'create' => 'invoices:write',
        'update' => 'invoices:write',
        'delete' => 'invoices:delete',
    ],
);
```

---

## Error handling

| Situation                        | HTTP response                          |
|----------------------------------|----------------------------------------|
| Record not found                 | `404 Not Found`                        |
| Missing required permission      | `403 Forbidden`                        |
| Invalid value / constraint error | `400 Bad Request` with DB error detail |

---

## Requirements

- PHP `^8.4`
- Laravel `^12.0`

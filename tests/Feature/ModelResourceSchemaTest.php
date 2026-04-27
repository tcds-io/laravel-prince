<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\AuthorizerContext;
use Tcds\Io\Prince\ModelResource;

class ModelResourceSchemaTest extends ModelResourceTestCase
{
    #[Test]
    public function schema_returns_the_resource_schema(): void
    {
        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJson([
            'resource' => 'invoices',
            'schema' => self::SCHEMA,
            'resources' => [],
        ]);
    }

    #[Test]
    public function schema_includes_crud_permissions(): void
    {
        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJsonPath('permissions.read', 'model:read');
        $response->assertJsonPath('permissions.create', 'model:create');
        $response->assertJsonPath('permissions.update', 'model:update');
        $response->assertJsonPath('permissions.delete', 'model:delete');
    }

    #[Test]
    public function schema_omits_permissions_for_disabled_endpoints(): void
    {
        ModelResource::of(TestInvoice::class, authorizer: fn(AuthorizerContext $context) => $context->permission === 'model:read', resourcePermissions: [
            'read' => 'model:read',
        ])->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJsonPath('permissions.read', 'model:read');
        $response->assertJsonMissingPath('permissions.create');
        $response->assertJsonMissingPath('permissions.update');
        $response->assertJsonMissingPath('permissions.delete');
    }

    #[Test]
    public function schema_omits_permissions_the_user_does_not_hold(): void
    {
        ModelResource::of(TestInvoice::class, authorizer: fn(AuthorizerContext $context) => $context->permission === 'model:read')->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJsonPath('permissions.read', 'model:read');
        $response->assertJsonMissingPath('permissions.create');
        $response->assertJsonMissingPath('permissions.update');
        $response->assertJsonMissingPath('permissions.delete');
    }

    #[Test]
    public function schema_is_always_accessible_regardless_of_permissions(): void
    {
        ModelResource::of(TestInvoice::class, authorizer: fn() => false)->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
    }
}

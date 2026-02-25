<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
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
    public function schema_returns_403_when_permission_missing(): void
    {
        // registerRoutes uses the default all-granted permissions; re-register with none
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertForbidden();
    }
}

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
    public function schema_is_always_accessible_regardless_of_permissions(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
    }
}

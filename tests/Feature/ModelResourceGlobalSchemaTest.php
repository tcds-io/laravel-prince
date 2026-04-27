<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\AuthorizerContext;
use Tcds\Io\Prince\ModelResourceBuilder;

class ModelResourceGlobalSchemaTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        ModelResourceBuilder::create()
            ->authorizer(fn() => true)
            ->resource(
                model: TestInvoice::class,
                resources: fn(ModelResourceBuilder $b) => $b->resource(TestItem::class),
            )
            ->resource(TestItem::class, segment: 'items')
            ->routes();
    }

    #[Test]
    public function global_schema_returns_all_registered_resources(): void
    {
        $response = $this->getJson('/_schema');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.resource', 'invoices');
        $response->assertJsonPath('data.1.resource', 'items');
    }

    #[Test]
    public function global_schema_includes_schema_and_nested_resources_per_entry(): void
    {
        $response = $this->getJson('/_schema');

        $response->assertOk();
        $response->assertJsonPath('data.0.resources', ['items']);
        $response->assertJsonPath('data.1.resources', []);
        $response->assertJsonPath('data.0.schema.0.name', 'id');
    }

    #[Test]
    public function global_schema_includes_permissions_filtered_by_user(): void
    {
        ModelResourceBuilder::create()
            ->authorizer(fn(AuthorizerContext $context) => $context->permission === 'default:model.read')
            ->resource(TestInvoice::class)
            ->resource(TestItem::class, segment: 'items')
            ->routes();

        $response = $this->getJson('/_schema');

        $response->assertOk();
        $response->assertJsonPath('data.0.permissions.read', 'default:model.read');
        $response->assertJsonMissingPath('data.0.permissions.create');
        $response->assertJsonMissingPath('data.0.permissions.update');
        $response->assertJsonMissingPath('data.0.permissions.delete');
    }

    #[Test]
    public function global_schema_is_always_accessible_regardless_of_permissions(): void
    {
        ModelResourceBuilder::create()
            ->authorizer(fn() => false)
            ->resource(TestInvoice::class)
            ->routes();

        $this->getJson('/_schema')->assertOk();
    }
}

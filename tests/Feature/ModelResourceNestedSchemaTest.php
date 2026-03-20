<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedSchemaTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function parent_schema_lists_nested_resource_names(): void
    {
        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJson([
            'resource' => 'invoices',
            'resources' => ['items'],
            'schema' => self::SCHEMA,
        ]);
    }

    #[Test]
    public function nested_schema_returns_the_nested_resource_schema(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items/_schema");

        $response->assertOk();
        $response->assertJson([
            'resource' => 'items',
            'schema' => self::NESTED_SCHEMA,
            'resources' => [],
        ]);
    }

    #[Test]
    public function get_meta_includes_nested_resource_names(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'resource' => 'invoices',
                'resources' => [
                    'items' => "/invoices/{$invoice->id}/items",
                ],
            ],
        ]);
    }
}

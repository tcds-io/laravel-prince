<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResourceBuilder;

class ModelResourceBuilderTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        ModelResourceBuilder::create()
            ->resource(
                model: TestInvoice::class,
                resources: fn(ModelResourceBuilder $b) => $b->resource(TestItem::class),
                globalSearch: true,
            )
            ->routes();
    }

    // --- resource routes ---

    #[Test]
    public function builder_registers_list_and_get_routes(): void
    {
        $invoice = TestInvoice::create(['title' => 'Test Invoice', 'amount' => 100.00]);

        $this->getJson('/invoices')->assertOk()->assertJsonPath('data.0.title', 'Test Invoice');
        $this->getJson("/invoices/{$invoice->id}")->assertOk()->assertJsonPath('data.title', 'Test Invoice');
    }

    #[Test]
    public function builder_registers_nested_routes_via_callback(): void
    {
        $invoice = TestInvoice::create(['title' => 'Test Invoice', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Widget', 'price' => 9.99]);

        $this->getJson("/invoices/{$invoice->id}/items")->assertOk()->assertJsonPath('data.0.description', 'Widget');
        $this->getJson("/invoices/{$invoice->id}/items/{$item->id}")->assertOk()->assertJsonPath('data.description', 'Widget');
    }

    // --- permissions ---

    #[Test]
    public function builder_applies_permissions_to_all_resources(): void
    {
        ModelResourceBuilder::create(userPermissions: [])->resource(TestInvoice::class)->routes();

        $this->getJson('/invoices')->assertForbidden();
    }

    // --- global search ---

    #[Test]
    public function builder_registers_global_search_for_opted_in_resources(): void
    {
        TestInvoice::create(['title' => 'Builder Invoice', 'amount' => 100.00]);

        $this->getJson('/search?q=Builder+Invoice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.resource', 'invoices');
    }
}

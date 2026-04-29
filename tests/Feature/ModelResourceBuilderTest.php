<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
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
        ModelResourceBuilder::create()->authorizer(fn() => false)->resource(TestInvoice::class, resourcePermissions: [
            'read' => 'model:read',
        ])->routes();

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

    // --- duplicate detection ---

    #[Test]
    public function routes_throws_when_two_models_share_the_same_table(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches("/route prefix 'invoices'/");

        ModelResourceBuilder::create()
            ->resource(TestInvoice::class)
            ->resource(TestDuplicateInvoice::class)
            ->routes();
    }

    #[Test]
    public function routes_does_not_throw_when_segment_disambiguates_duplicate_table(): void
    {
        ModelResourceBuilder::create()
            ->resource(TestInvoice::class)
            ->resource(TestDuplicateInvoice::class, segment: 'legacy-invoices')
            ->routes();

        $this->getJson('/invoices')->assertOk();
        $this->getJson('/legacy-invoices')->assertOk();
    }
}

class TestDuplicateInvoice extends Model
{
    protected $table = 'invoices';

    protected $fillable = ['title', 'amount'];
}

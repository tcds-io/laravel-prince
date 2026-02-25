<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

class ModelResourceFragmentNestedTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class, resources: [
            ModelResource::of(TestItem::class, fragment: 'line-items'),
        ])->routes();
    }

    #[Test]
    public function nested_routes_are_accessible_at_the_custom_fragment(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/line-items");

        $response->assertOk();
        $response->assertJson(['data' => [['description' => 'Item A']]]);
    }

    #[Test]
    public function nested_routes_are_not_accessible_at_the_table_name(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertNotFound();
    }

    #[Test]
    public function nested_meta_resource_still_reflects_the_table_name(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/line-items");

        $response->assertJson(['meta' => ['resource' => 'items']]);
    }
}

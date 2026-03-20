<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

/**
 * Verifies that GET /{resource}/{id} embeds registered sub-resources as inner lists
 * rather than relying on Eloquent $with eager loading.
 */
class ModelResourceGetInnerListTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class, resources: [
            ModelResource::of(TestItem::class, embed: true),
        ])->routes();
    }

    #[Test]
    public function get_embeds_nested_resource_records_in_data(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $itemA = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);
        $itemB = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $invoice->id,
                'title' => 'Invoice A',
                'items' => [
                    ['id' => $itemA->id, 'description' => 'Item A'],
                    ['id' => $itemB->id, 'description' => 'Item B'],
                ],
            ],
        ]);
    }

    #[Test]
    public function get_scopes_nested_records_to_the_parent(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        $itemA = TestItem::create(['invoice_id' => $invoiceA->id, 'description' => 'Item A', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoiceA->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data.items');
        $response->assertJson([
            'data' => [
                'items' => [
                    ['id' => $itemA->id],
                ],
            ],
        ]);
    }

    #[Test]
    public function get_includes_resource_link_for_each_nested_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'items' => [
                    ['id' => $item->id, '_resource' => "/invoices/{$invoice->id}/items/{$item->id}"],
                ],
            ],
        ]);
    }

    #[Test]
    public function get_embeds_empty_array_when_no_nested_records_exist(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'items' => [],
            ],
        ]);
    }
}

<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedGetTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function get_returns_the_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item B', 'price' => 12.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item C', 'price' => 14.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items/{$item->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'invoice_id' => $invoice->id,
                'description' => 'Item C',
                'price' => 14,
            ],
            'meta' => [
                'resource' => 'items',
                'schema' => self::NESTED_SCHEMA,
            ],
        ]);
    }

    #[Test]
    public function get_returns_404_for_unknown_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items/999");

        $response->assertNotFound();
    }

    #[Test]
    public function get_returns_404_when_item_belongs_to_another_invoice(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        $item = TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoiceA->id}/items/{$item->id}");

        $response->assertNotFound();
    }
}

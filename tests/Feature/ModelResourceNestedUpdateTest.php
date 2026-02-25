<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedUpdateTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function update_modifies_the_item_in_the_database(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->patchJson("/invoices/{$invoice->id}/items/{$item->id}", ['description' => 'Item A (updated)']);

        $response->assertNoContent();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'description' => 'Item A (updated)',
            'price' => 10.00,
        ]);
    }

    #[Test]
    public function update_does_not_allow_changing_the_invoice_id(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        $item = TestItem::create(['invoice_id' => $invoiceA->id, 'description' => 'Item A', 'price' => 10.00]);

        $this->patchJson("/invoices/{$invoiceA->id}/items/{$item->id}", ['invoice_id' => $invoiceB->id]);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'invoice_id' => $invoiceA->id]);
    }

    #[Test]
    public function update_returns_404_for_unknown_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->patchJson("/invoices/{$invoice->id}/items/999", ['description' => 'Item A (updated)']);

        $response->assertNotFound();
    }

    #[Test]
    public function update_returns_404_when_item_belongs_to_another_invoice(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        $item = TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->patchJson("/invoices/{$invoiceA->id}/items/{$item->id}", ['description' => 'Item B (updated)']);

        $response->assertNotFound();
    }
}

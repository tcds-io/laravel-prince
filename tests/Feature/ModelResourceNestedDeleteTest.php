<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedDeleteTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function delete_removes_the_item_from_the_database(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->deleteJson("/invoices/{$invoice->id}/items/{$item->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }

    #[Test]
    public function delete_returns_404_for_unknown_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->deleteJson("/invoices/{$invoice->id}/items/999");

        $response->assertNotFound();
    }

    #[Test]
    public function delete_returns_404_when_item_belongs_to_another_invoice(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        $item = TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->deleteJson("/invoices/{$invoiceA->id}/items/{$item->id}");

        $response->assertNotFound();
    }
}

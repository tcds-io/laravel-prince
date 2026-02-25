<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedCreateTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function create_persists_the_item_to_the_database(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $this->postJson("/invoices/{$invoice->id}/items", ['description' => 'Item A', 'price' => '10.00']);

        $this->assertDatabaseHas('items', [
            'invoice_id' => $invoice->id,
            'description' => 'Item A',
            'price' => 10.00,
        ]);
    }

    #[Test]
    public function create_returns_the_id_of_the_new_item(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->postJson("/invoices/{$invoice->id}/items", ['description' => 'Item A', 'price' => '10.00']);

        $response->assertExactJson(['id' => TestItem::first()->id]);
    }

    #[Test]
    public function create_always_sets_the_invoice_id_from_the_url(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $this->postJson("/invoices/{$invoiceA->id}/items", [
            'invoice_id' => $invoiceB->id,
            'description' => 'Item A',
            'price' => '10.00',
        ]);

        $this->assertDatabaseHas('items', ['invoice_id' => $invoiceA->id, 'description' => 'Item A']);
    }

    #[Test]
    public function create_returns_404_for_unknown_invoice(): void
    {
        $response = $this->postJson('/invoices/999/items', ['description' => 'Item A', 'price' => '10.00']);

        $response->assertNotFound();
    }
}

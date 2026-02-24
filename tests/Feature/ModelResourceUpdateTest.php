<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceUpdateTest extends ModelResourceTestCase
{
    #[Test]
    public function update_modifies_the_record_in_the_database(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->patchJson("/invoices/{$invoice->id}", ['title' => 'Invoice A (updated)']);

        $response->assertNoContent();
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'title' => 'Invoice A (updated)',
            'amount' => 100.00,
        ]);
    }

    #[Test]
    public function update_returns_404_for_unknown_id(): void
    {
        $response = $this->patchJson('/invoices/999', ['title' => 'Invoice A (updated)']);

        $response->assertNotFound();
    }
}

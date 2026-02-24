<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceDeleteTest extends ModelResourceTestCase
{
    #[Test]
    public function delete_removes_the_record_from_the_database(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->deleteJson("/invoices/{$invoice->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function delete_returns_404_for_unknown_id(): void
    {
        $response = $this->deleteJson('/invoices/999');

        $response->assertNotFound();
    }
}

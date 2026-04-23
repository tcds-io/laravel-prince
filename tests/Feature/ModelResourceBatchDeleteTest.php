<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceBatchDeleteTest extends ModelResourceTestCase
{
    #[Test]
    public function batch_delete_removes_all_records(): void
    {
        $a = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $b = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->deleteJson('/invoices', [$a->id, $b->id]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('invoices', ['id' => $a->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $b->id]);
    }

    #[Test]
    public function batch_delete_returns_404_when_any_id_is_unknown(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->deleteJson('/invoices', [$invoice->id, 999]);

        $response->assertNotFound();
    }

    #[Test]
    public function batch_delete_is_atomic_and_rolls_back_on_failure(): void
    {
        $a = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $this->deleteJson('/invoices', [$a->id, 999]);

        $this->assertDatabaseHas('invoices', ['id' => $a->id]);
    }
}

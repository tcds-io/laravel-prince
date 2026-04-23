<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceBatchUpdateTest extends ModelResourceTestCase
{
    #[Test]
    public function batch_update_modifies_all_records(): void
    {
        $a = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $b = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->patchJson('/invoices', [
            ['id' => $a->id, 'title' => 'Invoice A (updated)'],
            ['id' => $b->id, 'amount' => '300.00'],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('invoices', ['id' => $a->id, 'title' => 'Invoice A (updated)', 'amount' => 100.00]);
        $this->assertDatabaseHas('invoices', ['id' => $b->id, 'title' => 'Invoice B', 'amount' => 300.00]);
    }

    #[Test]
    public function batch_update_returns_404_when_any_id_is_unknown(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->patchJson('/invoices', [
            ['id' => $invoice->id, 'title' => 'Updated'],
            ['id' => 999, 'title' => 'Ghost'],
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function batch_update_is_atomic_and_rolls_back_on_failure(): void
    {
        $a = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $this->patchJson('/invoices', [
            ['id' => $a->id, 'title' => 'Updated'],
            ['id' => 999, 'title' => 'Ghost'],
        ]);

        $this->assertDatabaseHas('invoices', ['id' => $a->id, 'title' => 'Invoice A']);
    }
}

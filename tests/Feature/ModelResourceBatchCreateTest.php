<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceBatchCreateTest extends ModelResourceTestCase
{
    #[Test]
    public function batch_create_persists_all_records(): void
    {
        $this->postJson('/invoices', [
            ['title' => 'Invoice A', 'amount' => '100.00'],
            ['title' => 'Invoice B', 'amount' => '200.00'],
        ]);

        $this->assertDatabaseHas('invoices', ['title' => 'Invoice A', 'amount' => 100.00]);
        $this->assertDatabaseHas('invoices', ['title' => 'Invoice B', 'amount' => 200.00]);
    }

    #[Test]
    public function batch_create_returns_the_ids_of_all_new_records(): void
    {
        $response = $this->postJson('/invoices', [
            ['title' => 'Invoice A', 'amount' => '100.00'],
            ['title' => 'Invoice B', 'amount' => '200.00'],
        ]);

        $ids = TestInvoice::pluck('id')->all();

        $response->assertOk();
        $response->assertExactJson(['data' => [['id' => $ids[0]], ['id' => $ids[1]]]]);
    }

    #[Test]
    public function batch_create_is_atomic_and_rolls_back_on_failure(): void
    {
        $this->postJson('/invoices', [
            ['title' => 'Invoice A', 'amount' => '100.00'],
            ['title' => null, 'amount' => null],   // violates NOT NULL on title
        ]);

        $this->assertDatabaseMissing('invoices', ['title' => 'Invoice A']);
    }

    #[Test]
    public function single_create_still_works_with_object_body(): void
    {
        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertOk();
        $response->assertExactJson(['id' => TestInvoice::first()->id]);
    }
}

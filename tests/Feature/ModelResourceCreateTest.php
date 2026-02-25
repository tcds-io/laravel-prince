<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceCreateTest extends ModelResourceTestCase
{
    #[Test]
    public function create_persists_the_record_to_the_database(): void
    {
        $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $this->assertDatabaseHas('invoices', ['title' => 'Invoice A', 'amount' => 100.00]);
    }

    #[Test]
    public function create_returns_the_id_of_the_new_record(): void
    {
        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertOk();
        $response->assertExactJson(['id' => TestInvoice::first()->id]);
    }
}

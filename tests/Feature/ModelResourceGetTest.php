<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceGetTest extends ModelResourceTestCase
{
    #[Test]
    public function get_returns_the_record(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $invoice->id,
                'title' => 'Invoice A',
                'amount' => 100,
            ],
            'meta' => [
                'resource' => 'invoices',
                'schema' => self::SCHEMA,
            ],
        ]);
    }

    #[Test]
    public function get_returns_404_for_unknown_id(): void
    {
        $response = $this->getJson('/invoices/999');

        $response->assertNotFound();
    }
}

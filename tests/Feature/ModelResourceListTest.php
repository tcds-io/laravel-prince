<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceListTest extends ModelResourceTestCase
{
    #[Test]
    public function list_returns_paginated_records(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->getJson('/invoices');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['id' => $invoiceA->id, 'title' => 'Invoice A', 'amount' => 100],
                ['id' => $invoiceB->id, 'title' => 'Invoice B', 'amount' => 200],
            ],
            'meta' => [
                'resource' => 'invoices',
                'schema' => self::SCHEMA,
                'current_page' => 1,
                'per_page' => 10,
                'total' => 2,
                'last_page' => 1,
                'from' => 1,
                'to' => 2,
            ],
        ]);
    }

    #[Test]
    public function list_returns_empty_data_when_no_records(): void
    {
        $response = $this->getJson('/invoices');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [],
            'meta' => [
                'resource' => 'invoices',
                'schema' => self::SCHEMA,
                'current_page' => 1,
                'per_page' => 10,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
            ],
        ]);
    }
}

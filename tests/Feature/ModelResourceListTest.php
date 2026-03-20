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
                'current_page' => 1,
                'per_page' => 10,
                'total' => 2,
                'last_page' => 1,
            ],
        ]);
    }

    #[Test]
    public function list_includes_resource_link_for_each_item(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->getJson('/invoices');

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['id' => $invoiceA->id, '_resource' => "/invoices/{$invoiceA->id}"],
                ['id' => $invoiceB->id, '_resource' => "/invoices/{$invoiceB->id}"],
            ],
        ]);
    }

    #[Test]
    public function list_respects_custom_limit(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice C', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?limit=2');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.per_page', 2);
        $response->assertJsonPath('meta.total', 3);
        $response->assertJsonPath('meta.last_page', 2);
    }

    #[Test]
    public function list_caps_limit_at_100(): void
    {
        $response = $this->getJson('/invoices?limit=200');

        $response->assertOk();
        $response->assertJsonPath('meta.per_page', 100);
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
                'current_page' => 1,
                'per_page' => 10,
                'total' => 0,
                'last_page' => 1,
                'prev_page' => null,
                'next_page' => null,
            ],
        ]);
    }

    #[Test]
    public function list_includes_next_page_link_when_more_pages_exist(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice C', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?limit=2');

        $response->assertOk();
        $response->assertJsonPath('meta.prev_page', null);
        $this->assertStringContainsString('page=2', $response->json('meta.next_page'));
    }

    #[Test]
    public function list_includes_prev_page_link_on_subsequent_pages(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice C', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?limit=2&page=2');

        $response->assertOk();
        $this->assertStringContainsString('page=1', $response->json('meta.prev_page'));
        $response->assertJsonPath('meta.next_page', null);
    }
}

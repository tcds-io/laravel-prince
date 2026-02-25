<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedListTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function list_returns_paginated_items_for_a_given_invoice(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['description' => 'Item A', 'price' => 10],
            ],
            'meta' => [
                'resource' => 'items',
                'schema' => self::NESTED_SCHEMA,
                'current_page' => 1,
                'total' => 1,
                'last_page' => 1,
                'per_page' => 10,
            ],
        ]);
    }

    #[Test]
    public function list_returns_only_items_belonging_to_the_given_invoice(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestItem::create(['invoice_id' => $invoiceA->id, 'description' => 'Item A', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Item B', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoiceA->id}/items");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['description' => 'Item A']]]);
    }

    #[Test]
    public function list_returns_empty_data_when_the_invoice_has_no_items(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertOk();
        $response->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    #[Test]
    public function list_includes_resource_link_with_full_nested_path(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                ['id' => $item->id, '_resource' => "/invoices/{$invoice->id}/items/{$item->id}"],
            ],
        ]);
    }

    #[Test]
    public function list_returns_404_for_unknown_invoice(): void
    {
        $response = $this->getJson('/invoices/999/items');

        $response->assertNotFound();
    }
}

<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceNestedListFilterTest extends ModelResourceNestedTestCase
{
    #[Test]
    public function search_returns_items_matching_any_column(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $itemA = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Widget', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Gadget', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items?search=Widget");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $itemA->id]]]);
    }

    #[Test]
    public function prop_filter_returns_items_matching_the_given_prop(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $itemA = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Widget', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Gadget', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items?description=Widget");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $itemA->id]]]);
    }

    #[Test]
    public function prop_filter_is_scoped_to_the_given_invoice(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestItem::create(['invoice_id' => $invoiceA->id, 'description' => 'Widget', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoiceB->id, 'description' => 'Widget', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoiceA->id}/items?description=Widget");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    #[Test]
    public function prop_filter_applies_type_coercion_for_numeric_columns(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $itemA = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Widget', 'price' => 10.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Gadget', 'price' => 20.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items?price=10");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $itemA->id]]]);
    }
}

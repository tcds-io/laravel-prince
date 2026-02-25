<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceListFilterTest extends ModelResourceTestCase
{
    #[Test]
    public function search_returns_records_matching_any_column(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 200.00]);

        $response = $this->getJson('/invoices?search=Invoice Alpha');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id]]]);
    }

    #[Test]
    public function search_returns_empty_when_no_column_matches(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson('/invoices?search=not-existing');

        $response->assertOk();
        $response->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    #[Test]
    public function prop_filter_returns_records_matching_the_given_prop(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->getJson('/invoices?title=Invoice A');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id]]]);
    }

    #[Test]
    public function prop_filter_applies_type_coercion_for_numeric_columns(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);

        $response = $this->getJson('/invoices?amount=100');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id]]]);
    }

    #[Test]
    public function multiple_prop_filters_are_combined_with_and(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice B', 'amount' => 100.00]);

        $response = $this->getJson('/invoices?title=Invoice A&amount=100');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id]]]);
    }

    #[Test]
    public function prop_filter_returns_empty_when_no_match(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson('/invoices?title=Invoice Z');

        $response->assertOk();
        $response->assertJson(['data' => [], 'meta' => ['total' => 0]]);
    }

    #[Test]
    public function search_does_not_apply_to_datetime_columns(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        // Searching for the exact created_at value must not match; datetime columns
        // are excluded from search to avoid unreliable string comparisons in SQLite
        $response = $this->getJson('/invoices?' . http_build_query([
            'search' => $invoice->created_at->toDateTimeString(),
        ]));

        $response->assertOk();
        $response->assertJson(['meta' => ['total' => 0]]);
    }
}

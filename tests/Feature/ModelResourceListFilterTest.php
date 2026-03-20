<?php

declare(strict_types=1);

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
    public function search_with_like_applies_to_text_columns(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Receipt Gamma', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?' . http_build_query(['search' => 'Invoice%']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id], ['id' => $invoiceB->id]]]);
    }

    #[Test]
    public function search_like_is_ignored_for_numeric_columns(): void
    {
        TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        // %foo% skips numeric columns silently; the text columns won't match either, so total=0
        $response = $this->getJson('/invoices?' . http_build_query(['search' => '%999%']));

        $response->assertOk();
        $response->assertJson(['meta' => ['total' => 0]]);
    }

    #[Test]
    public function prop_filter_returns_400_for_like_on_numeric_column(): void
    {
        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '%100%']));

        $response->assertStatus(400);
    }

    #[Test]
    public function prop_filter_with_comma_separated_values_returns_matching_records(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice Gamma', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?' . http_build_query(['title' => 'Invoice Alpha,Invoice Beta']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id], ['id' => $invoiceB->id]]]);
    }

    #[Test]
    public function prop_filter_with_comma_separated_integers_returns_matching_records(): void
    {
        $invoiceA = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $invoiceB = TestInvoice::create(['title' => 'Invoice B', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Invoice C', 'amount' => 300.00]);

        $response = $this->getJson('/invoices?amount=100,200');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['id' => $invoiceA->id], ['id' => $invoiceB->id]]]);
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

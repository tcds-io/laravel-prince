<?php

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;

class ModelResourceListFilterOperatorsTest extends ModelResourceTestCase
{
    private function createInvoices(): void
    {
        TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 50.00]);
        TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 150.00]);
        TestInvoice::create(['title' => 'Invoice Gamma', 'amount' => 250.00]);
    }

    #[Test]
    public function greater_than_filters_numeric_column(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '>100']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Beta'], ['title' => 'Invoice Gamma']]]);
    }

    #[Test]
    public function less_than_filters_numeric_column(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '<200']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Alpha'], ['title' => 'Invoice Beta']]]);
    }

    #[Test]
    public function greater_than_or_equal_filters_numeric_column(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '>=150']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Beta'], ['title' => 'Invoice Gamma']]]);
    }

    #[Test]
    public function less_than_or_equal_filters_numeric_column(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '<=150']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Alpha'], ['title' => 'Invoice Beta']]]);
    }

    #[Test]
    public function between_filters_numeric_column(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['amount' => '100/200']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Beta']]]);
    }

    #[Test]
    public function like_filters_text_column_on_substring(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['title' => '%Beta%']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Beta']]]);
    }

    #[Test]
    public function like_filters_text_column_on_prefix(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['title' => 'Invoice%']));

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    #[Test]
    public function like_filters_text_column_on_suffix(): void
    {
        $this->createInvoices();

        $response = $this->getJson('/invoices?' . http_build_query(['title' => '%Gamma']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['title' => 'Invoice Gamma']]]);
    }
}

<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;
use Tcds\Io\Prince\ModelResourceGlobalSearch;

/**
 * Invoices are opted into global search; items are not.
 * This covers both matching behaviour and exclusion of non-searchable resources.
 */
class ModelResourceGlobalSearchTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class, globalSearch: true)->routes();
        ModelResource::of(TestItem::class, globalSearch: false)->routes();

        ModelResourceGlobalSearch::routes();
    }

    #[Test]
    public function search_returns_matching_results_for_opted_in_resources(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 200.00]);

        $response = $this->getJson('/search?q=Invoice+Alpha');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'id' => $invoice->id,
                    'description' => 'Invoice Alpha',
                    'resource' => 'invoices',
                    'link' => "/invoices/{$invoice->id}",
                ],
            ],
        ]);
    }

    #[Test]
    public function search_supports_like_operator(): void
    {
        TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);
        TestInvoice::create(['title' => 'Invoice Beta', 'amount' => 200.00]);
        TestInvoice::create(['title' => 'Something else', 'amount' => 300.00]);

        $response = $this->getJson('/search?q=%25Invoice%25');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function search_excludes_resources_with_global_search_disabled(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Unique Item Description', 'price' => 10.00]);

        $response = $this->getJson('/search?q=Unique+Item+Description');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function search_returns_empty_data_when_no_matches(): void
    {
        TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);

        $response = $this->getJson('/search?q=NoMatchAnywhere');

        $response->assertOk();
        $response->assertJson(['data' => []]);
    }

    #[Test]
    public function search_returns_empty_data_when_q_is_missing(): void
    {
        TestInvoice::create(['title' => 'Invoice Alpha', 'amount' => 100.00]);

        $response = $this->getJson('/search');

        $response->assertOk();
        $response->assertJson(['data' => []]);
    }
}

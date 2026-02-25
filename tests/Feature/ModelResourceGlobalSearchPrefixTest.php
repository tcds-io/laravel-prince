<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResourceBuilder;

/**
 * Verifies that global search links include the outer Route::prefix() group,
 * e.g. "/api/backoffice/invoices/1" instead of just "/invoices/1".
 */
class ModelResourceGlobalSearchPrefixTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        Route::prefix('/api/backoffice')->group(function () {
            ModelResourceBuilder::create()
                ->resource(model: TestInvoice::class, globalSearch: true)
                ->routes();
        });
    }

    #[Test]
    public function search_link_includes_outer_route_prefix(): void
    {
        $invoice = TestInvoice::create(['title' => 'Prefixed Invoice', 'amount' => 50.00]);

        $response = $this->getJson('/api/backoffice/search?q=Prefixed+Invoice');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'id' => $invoice->id,
                    'description' => 'Prefixed Invoice',
                    'resource' => 'invoices',
                    'link' => "/api/backoffice/invoices/{$invoice->id}",
                ],
            ],
        ]);
    }
}

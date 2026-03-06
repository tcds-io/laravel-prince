<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

class ModelResourceFragmentTest extends ModelResourceTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class, segment: 'my-invoices')->routes();
    }

    #[Test]
    public function routes_are_accessible_at_the_custom_segment(): void
    {
        $response = $this->getJson('/my-invoices');

        $response->assertOk();
    }

    #[Test]
    public function routes_are_not_accessible_at_the_table_name(): void
    {
        $response = $this->getJson('/invoices');

        $response->assertNotFound();
    }

    #[Test]
    public function meta_resource_still_reflects_the_table_name(): void
    {
        $response = $this->getJson('/my-invoices');

        $response->assertJson(['meta' => ['resource' => 'invoices']]);
    }
}

<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

class ModelResourcePermissionsTest extends ModelResourceTestCase
{
    protected function registerRoutes(): void
    {
        // Routes are registered per-test with specific permissions.
    }

    // --- list ---

    #[Test]
    public function list_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();

        $response = $this->getJson('/invoices');

        $response->assertForbidden();
    }

    #[Test]
    public function list_is_accessible_with_list_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:list'])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    // --- get ---

    #[Test]
    public function get_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function get_is_accessible_with_get_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:get'])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
    }

    // --- create ---

    #[Test]
    public function create_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();

        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertForbidden();
    }

    #[Test]
    public function create_is_accessible_with_create_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:create'])->routes();

        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertOk();
    }

    // --- update ---

    #[Test]
    public function update_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->patchJson("/invoices/{$invoice->id}", ['title' => 'Invoice A (updated)']);

        $response->assertForbidden();
    }

    #[Test]
    public function update_is_accessible_with_update_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:update'])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->patchJson("/invoices/{$invoice->id}", ['title' => 'Invoice A (updated)']);

        $response->assertNoContent();
    }

    // --- delete ---

    #[Test]
    public function delete_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->deleteJson("/invoices/{$invoice->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function delete_is_accessible_with_delete_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:delete'])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->deleteJson("/invoices/{$invoice->id}");

        $response->assertNoContent();
    }
}

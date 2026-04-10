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

    // --- public ---

    #[Test]
    public function public_permission_allows_access_without_matching_user_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [], resourcePermissions: [
            'list' => 'public',
            'get' => 'public',
            'create' => 'model:create',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    // --- disabled ---

    #[Test]
    public function disabled_permission_does_not_register_route(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:list', 'model:get', 'model:create', 'model:update', 'model:delete'], resourcePermissions: [
            'list' => 'model:list',
            'get' => 'model:get',
            'create' => 'disabled',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertMethodNotAllowed();
    }

    #[Test]
    public function disabled_permission_still_allows_other_actions(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:list', 'model:get', 'model:create', 'model:update', 'model:delete'], resourcePermissions: [
            'list' => 'model:list',
            'get' => 'model:get',
            'create' => 'disabled',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    #[Test]
    public function schema_is_accessible_even_when_list_is_disabled(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [], resourcePermissions: [
            'list' => 'disabled',
            'get' => 'disabled',
            'create' => 'disabled',
            'update' => 'disabled',
            'delete' => 'disabled',
        ])->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
    }
}

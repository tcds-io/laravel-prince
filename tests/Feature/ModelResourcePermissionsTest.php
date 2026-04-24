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

    // --- read (list + get) ---

    #[Test]
    public function list_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();

        $response = $this->getJson('/invoices');

        $response->assertForbidden();
    }

    #[Test]
    public function list_is_accessible_with_read_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:read'])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    #[Test]
    public function get_returns_403_when_permission_is_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [])->routes();
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function get_is_accessible_with_read_permission(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:read'])->routes();
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
            'read' => 'public',
            'create' => 'model:create',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    // --- missing key = endpoint not registered ---

    #[Test]
    public function missing_permission_key_does_not_register_route(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:read', 'model:create', 'model:update', 'model:delete'], resourcePermissions: [
            'read' => 'model:read',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->postJson('/invoices', ['title' => 'Invoice A', 'amount' => '100.00']);

        $response->assertMethodNotAllowed();
    }

    #[Test]
    public function missing_permission_key_still_allows_other_actions(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => ['model:read', 'model:create', 'model:update', 'model:delete'], resourcePermissions: [
            'read' => 'model:read',
            'update' => 'model:update',
            'delete' => 'model:delete',
        ])->routes();

        $response = $this->getJson('/invoices');

        $response->assertOk();
    }

    #[Test]
    public function schema_is_accessible_even_when_all_permission_keys_are_missing(): void
    {
        ModelResource::of(TestInvoice::class, userPermissions: fn() => [], resourcePermissions: [])->routes();

        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
    }
}

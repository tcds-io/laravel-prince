<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\AuthorizerContext;
use Tcds\Io\Prince\ModelResource;

/**
 * Verifies that nested resource actions are forbidden when the user lacks
 * the required "read" permission on the parent resource.
 */
class ModelResourceNestedPermissionsTest extends ModelResourceNestedTestCase
{
    /** Parent (invoice) has NO model:read permission; nested (item) has full permissions. */
    protected function registerRoutes(): void
    {
        ModelResource::of(
            TestInvoice::class,
            authorizer: fn(AuthorizerContext $context) => in_array($context->permission, ['model:create', 'model:update', 'model:delete']),
            permissions: [
                'read'   => 'model:read',
                'create' => 'model:create',
                'update' => 'model:update',
                'delete' => 'model:delete',
            ],
            resources: [ModelResource::of(TestItem::class)],
        )->routes();
    }

    #[Test]
    public function list_returns_403_when_parent_permission_is_missing(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertForbidden();
    }

    #[Test]
    public function get_returns_403_when_parent_permission_is_missing(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items/{$item->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function create_returns_403_when_parent_permission_is_missing(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->postJson("/invoices/{$invoice->id}/items", ['description' => 'Item A', 'price' => '10.00']);

        $response->assertForbidden();
    }

    #[Test]
    public function update_returns_403_when_parent_permission_is_missing(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->patchJson("/invoices/{$invoice->id}/items/{$item->id}", ['description' => 'Item A (updated)']);

        $response->assertForbidden();
    }

    #[Test]
    public function delete_returns_403_when_parent_permission_is_missing(): void
    {
        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);
        $item = TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->deleteJson("/invoices/{$invoice->id}/items/{$item->id}");

        $response->assertForbidden();
    }

    #[Test]
    public function nested_actions_are_accessible_when_parent_permission_is_present(): void
    {
        ModelResource::of(
            TestInvoice::class,
            authorizer: fn() => true,
            resources: [ModelResource::of(TestItem::class)],
        )->routes();

        $invoice = TestInvoice::create(['title' => 'Invoice A', 'amount' => 100.00]);

        $response = $this->getJson("/invoices/{$invoice->id}/items");

        $response->assertOk();
    }
}

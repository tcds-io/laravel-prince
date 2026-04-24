<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Tcds\Io\Prince\ModelResource;
use Tcds\Io\Prince\ResourceAction;
use Test\Tcds\Io\Prince\Stubs\Actions\ExportInvoicesAction;
use Test\Tcds\Io\Prince\Stubs\Actions\SearchCustomAction;
use Test\Tcds\Io\Prince\Stubs\Actions\SendInvoiceAction;

class ModelResourceActionsTest extends ModelResourceTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(
            model: TestInvoice::class,
            actions: [
                ResourceAction::get(
                    path: '/export',
                    action: ExportInvoicesAction::class,
                ),
                ResourceAction::get(
                    path: '/search-custom',
                    action: SearchCustomAction::class,
                ),
                ResourceAction::post(
                    path: '/{id}/send',
                    action: SendInvoiceAction::class,
                    permission: 'invoices:send',
                ),
            ],
            userPermissions: fn() => ['model:read', 'model:create', 'model:update', 'model:delete', 'invoices:send'],
        )->routes();
    }

    public function test_collection_action_is_accessible(): void
    {
        $response = $this->get('/invoices/export');

        $response->assertStatus(200);
        $response->assertJson(['exported' => true]);
    }

    public function test_collection_action_does_not_conflict_with_get_single(): void
    {
        // 'export' must not be captured as a {resourceId} param
        $invoice = TestInvoice::create(['title' => 'Test', 'amount' => 10.0]);

        $this->get('/invoices/' . $invoice->id)->assertStatus(200);
        $this->get('/invoices/export')->assertStatus(200);
    }

    public function test_item_action_resolves_and_injects_model(): void
    {
        $invoice = TestInvoice::create(['title' => 'Test', 'amount' => 10.0]);

        $response = $this->post('/invoices/' . $invoice->id . '/send');

        $response->assertStatus(200);
        $response->assertJson(['sent' => $invoice->id]);
    }

    public function test_item_action_returns_404_for_missing_record(): void
    {
        $response = $this->post('/invoices/999/send');

        $response->assertStatus(404);
    }

    public function test_item_action_is_forbidden_when_permission_is_missing(): void
    {
        ModelResource::of(
            model: TestInvoice::class,
            actions: [
                ResourceAction::post(
                    path: '/{id}/send',
                    action: SendInvoiceAction::class,
                    permission: 'invoices:send',
                ),
            ],
            userPermissions: fn() => [], // no permissions
            resourcePermissions: [
                'read' => 'model:read',
                'create' => 'model:create',
                'update' => 'model:update',
                'delete' => 'model:delete',
            ],
        )->routes();

        $invoice = TestInvoice::create(['title' => 'Test', 'amount' => 10.0]);

        $this->post('/invoices/' . $invoice->id . '/send')->assertStatus(403);
    }

    public function test_collection_action_receives_request_via_ioc(): void
    {
        $response = $this->get('/invoices/search-custom?q=hello');

        $response->assertStatus(200);
        $response->assertJson(['q' => 'hello']);
    }
}

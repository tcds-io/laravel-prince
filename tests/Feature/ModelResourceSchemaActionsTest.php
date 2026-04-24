<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResourceBuilder;
use Tcds\Io\Prince\ResourceAction;

class ModelResourceSchemaActionsTest extends ModelResourceTestCase
{
    protected function registerRoutes(): void
    {
        ModelResourceBuilder::create(userPermissions: fn() => ['invoices:import', 'invoices:preview'])
            ->resource(TestInvoice::class, actions: [
                ResourceAction::post('/import', TestSchemaImportAction::class, permission: 'invoices:import'),
                ResourceAction::get('/{id}/preview', TestSchemaImportAction::class, permission: 'invoices:preview'),
                ResourceAction::delete('/purge', TestSchemaImportAction::class),
            ])
            ->routes();
    }

    #[Test]
    public function schema_includes_action_permissions_as_slugs(): void
    {
        $response = $this->getJson('/invoices/_schema');

        $response->assertOk();
        $response->assertJsonPath('permissions.post-import', 'invoices:import');
        $response->assertJsonPath('permissions.get-id-preview', 'invoices:preview');
        $response->assertJsonMissingPath('permissions.delete-purge'); // null permission — omitted
    }
}

class TestSchemaImportAction
{
    public function __invoke(): void {}
}

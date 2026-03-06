<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\Events\ResourceCreated;
use Tcds\Io\Prince\Events\ResourceCreating;
use Tcds\Io\Prince\Events\ResourceDeleted;
use Tcds\Io\Prince\Events\ResourceDeleting;
use Tcds\Io\Prince\Events\ResourceUpdated;
use Tcds\Io\Prince\Events\ResourceUpdating;
use Tcds\Io\Prince\ModelResource;

class ModelResourceEventsTest extends ModelResourceTestCase
{
    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class)->routes();
    }

    #[Test]
    public function creating_event_is_dispatched_with_model_name_and_data(): void
    {
        Event::fake();

        $this->postJson('/invoices', ['title' => 'Test', 'amount' => 10.0]);

        Event::assertDispatched(ResourceCreating::class, function (ResourceCreating $event): bool {
            return $event->modelName === TestInvoice::class
                && $event->data['title'] === 'Test'
                && $event->data['amount'] === 10.0;
        });
    }

    #[Test]
    public function created_event_is_dispatched_with_model_instance(): void
    {
        Event::fake();

        $this->postJson('/invoices', ['title' => 'Test', 'amount' => 10.0]);

        Event::assertDispatched(ResourceCreated::class, function (ResourceCreated $event): bool {
            return $event->model instanceof TestInvoice
                && $event->model->title === 'Test';
        });
    }

    #[Test]
    public function creating_listener_can_mutate_data_before_save(): void
    {
        Event::listen(ResourceCreating::class, function (ResourceCreating $event): void {
            $event->data['title'] = strtoupper($event->data['title']);
        });

        $this->postJson('/invoices', ['title' => 'test invoice', 'amount' => 10.0]);

        $this->assertDatabaseHas('invoices', ['title' => 'TEST INVOICE']);
    }

    #[Test]
    public function updating_event_is_dispatched_with_model_and_changed_data(): void
    {
        $invoice = TestInvoice::create(['title' => 'Original', 'amount' => 10.0]);

        Event::fake();

        $this->patchJson('/invoices/' . $invoice->id, ['title' => 'Updated']);

        Event::assertDispatched(ResourceUpdating::class, function (ResourceUpdating $event) use ($invoice): bool {
            return $event->model->id === $invoice->id
                && $event->data['title'] === 'Updated';
        });
    }

    #[Test]
    public function updated_event_is_dispatched_with_model_instance(): void
    {
        $invoice = TestInvoice::create(['title' => 'Original', 'amount' => 10.0]);

        Event::fake();

        $this->patchJson('/invoices/' . $invoice->id, ['title' => 'Updated']);

        Event::assertDispatched(ResourceUpdated::class, function (ResourceUpdated $event) use ($invoice): bool {
            return $event->model->id === $invoice->id;
        });
    }

    #[Test]
    public function updating_listener_can_mutate_data_before_save(): void
    {
        $invoice = TestInvoice::create(['title' => 'original', 'amount' => 10.0]);

        Event::listen(ResourceUpdating::class, function (ResourceUpdating $event): void {
            $event->data['title'] = strtoupper($event->data['title']);
        });

        $this->patchJson('/invoices/' . $invoice->id, ['title' => 'updated title']);

        $this->assertDatabaseHas('invoices', ['title' => 'UPDATED TITLE']);
    }

    #[Test]
    public function deleting_event_is_dispatched_with_model_instance(): void
    {
        $invoice = TestInvoice::create(['title' => 'Test', 'amount' => 10.0]);

        Event::fake();

        $this->deleteJson('/invoices/' . $invoice->id);

        Event::assertDispatched(ResourceDeleting::class, function (ResourceDeleting $event) use ($invoice): bool {
            return $event->model->id === $invoice->id;
        });
    }

    #[Test]
    public function deleted_event_is_dispatched_with_model_id(): void
    {
        $invoice = TestInvoice::create(['title' => 'Test', 'amount' => 10.0]);
        $id = $invoice->id;

        Event::fake();

        $this->deleteJson('/invoices/' . $id);

        Event::assertDispatched(ResourceDeleted::class, function (ResourceDeleted $event) use ($id): bool {
            return $event->modelId === $id;
        });
    }
}

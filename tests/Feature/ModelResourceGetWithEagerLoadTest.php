<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

/**
 * Verifies that $with eager loads defined on the model do not pollute GET responses.
 * Only explicitly registered nested resources should appear in data.
 */
class ModelResourceGetWithEagerLoadTest extends ModelResourceNestedTestCase
{
    protected function registerRoutes(): void
    {
        // Intentionally no nested resources — $with on the model must not bleed through
        ModelResource::of(TestInvoiceWithEagerLoad::class)->routes();
    }

    #[Test]
    public function get_does_not_include_relations_from_with(): void
    {
        $invoice = TestInvoiceWithEagerLoad::create(['title' => 'Invoice A', 'amount' => 100.00]);
        TestItem::create(['invoice_id' => $invoice->id, 'description' => 'Item A', 'price' => 10.00]);

        $response = $this->getJson("/invoices/{$invoice->id}");

        $response->assertOk();
        $response->assertJsonMissingPath('data.items');
    }
}

class TestInvoiceWithEagerLoad extends Model
{
    protected $table = 'invoices';

    protected $with = ['items'];

    protected $casts = [
        'amount' => 'float',
    ];

    protected $fillable = [
        'title',
        'amount',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TestItem::class, 'invoice_id');
    }
}

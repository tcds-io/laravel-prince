<?php

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Tcds\Io\Prince\ModelResource;

abstract class ModelResourceTestCase extends TestCase
{
    protected const array SCHEMA = [
        ['name' => 'id', 'type' => 'integer'],
        ['name' => 'title', 'type' => 'varchar'],
        ['name' => 'amount', 'type' => 'number'],
        ['name' => 'created_at', 'type' => 'datetime'],
        ['name' => 'updated_at', 'type' => 'datetime'],
    ];

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('amount', 8, 2);
            $table->timestamps();
        });

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class);
    }
}

class TestInvoice extends Model
{
    protected $table = 'invoices';

    protected $casts = [
        'amount' => 'float',
    ];

    protected $fillable = [
        'title',
        'amount',
    ];
}

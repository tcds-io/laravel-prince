<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tcds\Io\Prince\ModelResource;

abstract class ModelResourceNestedTestCase extends ModelResourceTestCase
{
    protected const array NESTED_SCHEMA = [
        ['name' => 'id',          'type' => 'integer'],
        ['name' => 'invoice_id',  'type' => 'integer'],
        ['name' => 'description', 'type' => 'varchar'],
        ['name' => 'price',       'type' => 'number'],
        ['name' => 'created_at',  'type' => 'datetime'],
        ['name' => 'updated_at',  'type' => 'datetime'],
    ];

    protected function createTables(): void
    {
        parent::createTables();

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained();
            $table->string('description');
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }

    protected function registerRoutes(): void
    {
        ModelResource::of(TestInvoice::class, resources: [
            ModelResource::of(TestItem::class),
        ])->routes();
    }
}

class TestItem extends Model
{
    protected $table = 'items';

    protected $casts = [
        'price' => 'float',
    ];

    protected $fillable = [
        'invoice_id',
        'description',
        'price',
    ];
}

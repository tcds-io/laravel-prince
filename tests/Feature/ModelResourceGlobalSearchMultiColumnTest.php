<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResourceBuilder;

/**
 * A resource with two text columns — proves that a record matching on multiple
 * columns appears exactly once and that the first matching column wins as description.
 */
class ModelResourceGlobalSearchMultiColumnTest extends ModelResourceTestCase
{
    protected function createTables(): void
    {
        parent::createTables();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku');
            $table->timestamps();
        });
    }

    protected function registerRoutes(): void
    {
        ModelResourceBuilder::create()
            ->resource(model: TestProduct::class, globalSearch: true)
            ->routes();
    }

    #[Test]
    public function search_returns_each_record_once_when_multiple_columns_match(): void
    {
        $product = TestProduct::create(['name' => 'foo widget', 'sku' => 'foo-001']);

        $response = $this->getJson('/search?q=%25foo%25');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $product->id);
    }

    #[Test]
    public function search_description_is_the_first_matching_column(): void
    {
        TestProduct::create(['name' => 'blue widget', 'sku' => 'foo-001']);

        // 'foo' only in sku, so description must come from sku
        $response = $this->getJson('/search?q=%25foo%25');

        $response->assertOk();
        $response->assertJsonPath('data.0.description', 'foo-001');
    }
}

class TestProduct extends Model
{
    protected $table = 'products';

    protected $fillable = ['name', 'sku'];
}

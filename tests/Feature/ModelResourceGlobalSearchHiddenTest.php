<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResourceBuilder;

/**
 * Verifies that columns listed in a model's $hidden array are excluded
 * from global search queries so sensitive data (e.g. passwords) is never exposed.
 */
class ModelResourceGlobalSearchHiddenTest extends TestCase
{
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

        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });

        ModelResourceBuilder::create()
            ->resource(TestAccount::class, globalSearch: true)
            ->routes();
    }

    #[Test]
    public function search_does_not_query_hidden_columns(): void
    {
        TestAccount::create(['email' => 'alice@example.com', 'password' => 'secret-password']);

        $response = $this->getJson('/search?q=secret-password');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function search_still_queries_non_hidden_columns(): void
    {
        $account = TestAccount::create(['email' => 'alice@example.com', 'password' => 'secret-password']);

        $response = $this->getJson('/search?q=alice@example.com');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson([
            'data' => [
                [
                    'id' => $account->id,
                    'description' => 'alice@example.com',
                    'resource' => 'accounts',
                ],
            ],
        ]);
    }
}

class TestAccount extends Model
{
    protected $table = 'accounts';

    protected $fillable = ['email', 'password'];

    protected $hidden = ['password'];
}

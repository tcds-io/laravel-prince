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
 * Verifies that GET /{resource}/{id} embeds a belongs-to related resource
 * as a single object (or null) rather than an array.
 */
class ModelResourceGetInnerSingleTest extends TestCase
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

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('company_id')->nullable()->constrained();
            $table->timestamps();
        });

        ModelResourceBuilder::create()
            ->resource(
                TestBelongsToUser::class,
                resources: fn($b) => $b->belongsTo(TestBelongsToCompany::class, embed: true),
            )
            ->resource(TestBelongsToCompany::class)
            ->routes();
    }

    #[Test]
    public function get_embeds_belongs_to_resource_as_single_object(): void
    {
        $company = TestBelongsToCompany::create(['name' => 'Acme']);
        $user = TestBelongsToUser::create(['name' => 'Alice', 'company_id' => $company->id]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'name' => 'Alice',
                'company' => [
                    'id' => $company->id,
                    'name' => 'Acme',
                ],
            ],
        ]);
    }

    #[Test]
    public function get_embeds_null_when_foreign_key_is_null(): void
    {
        $user = TestBelongsToUser::create(['name' => 'Bob', 'company_id' => null]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'id' => $user->id,
                'company' => null,
            ],
        ]);
    }

    #[Test]
    public function get_includes_resource_link_for_the_embedded_object(): void
    {
        $company = TestBelongsToCompany::create(['name' => 'Acme']);
        $user = TestBelongsToUser::create(['name' => 'Alice', 'company_id' => $company->id]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'company' => [
                    '_resource' => "/users/{$user->id}/companies/{$company->id}",
                ],
            ],
        ]);
    }

    #[Test]
    public function meta_resources_includes_full_path_with_fk_id(): void
    {
        $company = TestBelongsToCompany::create(['name' => 'Acme']);
        $user = TestBelongsToUser::create(['name' => 'Alice', 'company_id' => $company->id]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'resources' => [
                    'company' => "/users/{$user->id}/companies/{$company->id}",
                ],
            ],
        ]);
    }

    #[Test]
    public function meta_resources_link_is_null_when_foreign_key_is_null(): void
    {
        $user = TestBelongsToUser::create(['name' => 'Bob', 'company_id' => null]);

        $response = $this->getJson("/users/{$user->id}");

        $response->assertOk();
        $response->assertJson([
            'meta' => [
                'resources' => [
                    'company' => null,
                ],
            ],
        ]);
    }

    #[Test]
    public function belongs_to_does_not_register_nested_list_route(): void
    {
        $company = TestBelongsToCompany::create(['name' => 'Acme']);
        $user = TestBelongsToUser::create(['name' => 'Alice', 'company_id' => $company->id]);

        $response = $this->getJson("/users/{$user->id}/companies");

        $response->assertNotFound();
    }
}

class TestBelongsToCompany extends Model
{
    protected $table = 'companies';

    protected $fillable = ['name'];
}

class TestBelongsToUser extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'company_id'];
}

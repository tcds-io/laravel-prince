<?php

namespace Test\Tcds\Io\Prince\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tcds\Io\Prince\ModelResource;

enum TestCurrency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case EGP = 'EGP';
}

class TestPayment extends Model
{
    protected $table = 'payments';
    protected $casts = ['currency' => TestCurrency::class];
    protected $fillable = ['amount', 'currency'];
}

class ModelResourceListFilterEnumTest extends ModelResourceTestCase
{
    protected function createTables(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->decimal('amount', 8, 2);
            $table->string('currency');
            $table->timestamps();
        });
    }

    protected function registerRoutes(): void
    {
        ModelResource::of(TestPayment::class)->routes();
    }

    #[Test]
    public function enum_exact_match_returns_records_with_matching_value(): void
    {
        TestPayment::create(['amount' => 100, 'currency' => TestCurrency::EUR]);
        TestPayment::create(['amount' => 200, 'currency' => TestCurrency::USD]);

        $response = $this->getJson('/payments?currency=EUR');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJson(['data' => [['currency' => 'EUR']]]);
    }

    #[Test]
    public function enum_like_matches_backed_string_values_with_wildcard(): void
    {
        TestPayment::create(['amount' => 100, 'currency' => TestCurrency::EUR]);
        TestPayment::create(['amount' => 150, 'currency' => TestCurrency::EGP]);
        TestPayment::create(['amount' => 200, 'currency' => TestCurrency::USD]);

        // E% should match EUR and EGP but not USD
        $response = $this->getJson('/payments?' . http_build_query(['currency' => 'E%']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJson(['data' => [['currency' => 'EUR'], ['currency' => 'EGP']]]);
    }

    #[Test]
    public function enum_exact_match_returns_400_for_unknown_enum_value(): void
    {
        TestPayment::create(['amount' => 100, 'currency' => TestCurrency::EUR]);

        // GBP is not a valid TestCurrency case; the parser throws, returning 400
        $response = $this->getJson('/payments?currency=GBP');

        $response->assertBadRequest();
    }
}

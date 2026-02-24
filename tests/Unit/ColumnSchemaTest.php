<?php

namespace Test\Tcds\Io\Prince\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tcds\Io\Prince\ColumnSchema;

class ColumnSchemaTest extends TestCase
{
    #[Test]
    public function json_serialize_without_values(): void
    {
        $schema = new ColumnSchema(
            name: 'title',
            type: 'string',
            parser: fn($v) => $v,
        );

        $result = $schema->jsonSerialize();

        $this->assertEquals(['name' => 'title', 'type' => 'string'], $result);
    }

    #[Test]
    public function json_serialize_with_values(): void
    {
        $schema = new ColumnSchema(
            name: 'status',
            type: 'enum',
            parser: fn($v) => $v,
            values: ['active', 'inactive'],
        );

        $result = $schema->jsonSerialize();

        $this->assertEquals([
            'name' => 'status',
            'type' => 'enum',
            'values' => ['active', 'inactive'],
        ], $result);
    }

    #[Test]
    public function value_of_returns_parsed_value_from_request(): void
    {
        $schema = new ColumnSchema(
            name: 'age',
            type: 'integer',
            parser: fn($v) => (int) $v,
        );
        $request = Request::create('/', 'POST', ['age' => '42']);

        $result = $schema->valueOf($request);

        $this->assertSame(42, $result);
    }

    #[Test]
    public function value_of_applies_parser_to_the_input_value(): void
    {
        $schema = new ColumnSchema(
            name: 'price',
            type: 'number',
            parser: fn($v) => (float) $v,
        );
        $request = Request::create('/', 'POST', ['price' => '9.99']);

        $result = $schema->valueOf($request);

        $this->assertSame(9.99, $result);
    }

    #[Test]
    public function value_of_returns_null_when_field_is_absent_from_request(): void
    {
        $schema = new ColumnSchema(
            name: 'age',
            type: 'integer',
            parser: fn($v) => (int) $v,
        );
        $request = Request::create('/', 'POST', []);

        $result = $schema->valueOf($request);

        $this->assertNull($result);
    }
}

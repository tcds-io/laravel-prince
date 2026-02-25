<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class ModelResourceGlobalSearch
{
    /**
     * @param list<array{table: string, routePrefix: string, schema: list<ColumnSchema>}> $entries
     */
    private function __construct(private array $entries) {}

    /**
     * Builds a ModelResourceGlobalSearch from a list of resources. Only resources with
     * globalSearch = true are included. Call ->routes() on the result to register the search route.
     *
     * @param list<ModelResource> $resources
     */
    public static function of(array $resources): self
    {
        $entries = [];

        foreach ($resources as $resource) {
            if ($resource->globalSearch) {
                $entries[] = $resource->searchData();
            }
        }

        return new self($entries);
    }

    /**
     * Registers GET /search returning { data: [{ id, description, resource, link }] }.
     *
     * Accepts ?q=value where value follows the same operator syntax as column filters:
     *   %foo%  → LIKE on all text/enum columns
     *   foo    → exact match on all text/enum columns
     * Numeric and datetime columns are always skipped.
     * Columns where the value cannot be parsed (e.g. invalid enum cases) are silently skipped.
     */
    public function routes(): void
    {
        $entries = $this->entries;

        Route::get('/search', function (Request $request) use ($entries) {
            $q = $request->query('q');

            if (!is_string($q) || $q === '') {
                return response()->json(['data' => []]);
            }

            $unions = [];
            $bindings = [];

            foreach ($entries as ['table' => $table, 'routePrefix' => $prefix, 'schema' => $schema]) {
                $linkExpr = self::linkSql($prefix);

                foreach ($schema as $column) {
                    if (in_array($column->type, ['integer', 'number', 'datetime'], true)) {
                        continue;
                    }

                    try {
                        [$operator, $value] = ModelResourceQuery::parseFilter($column, $q);
                    } catch (BadRequestHttpException) {
                        continue;
                    }

                    $unions[] = "SELECT id, `{$column->name}` AS description, '{$table}' AS resource, {$linkExpr} AS link"
                        . " FROM `{$table}` WHERE `{$column->name}` {$operator} ?";
                    $bindings[] = $value instanceof BackedEnum ? $value->value : $value;
                }
            }

            if ($unions === []) {
                return response()->json(['data' => []]);
            }

            $sql = implode(' UNION ', $unions);
            $results = DB::select($sql, $bindings);

            return response()->json(['data' => $results]);
        });
    }

    /**
     * Returns the SQL expression for building the resource link column, adapting to the DB driver.
     */
    private static function linkSql(string $routePrefix): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "'/{$routePrefix}/' || CAST(id AS TEXT)",
            default => "CONCAT('/{$routePrefix}/', id)",
        };
    }
}

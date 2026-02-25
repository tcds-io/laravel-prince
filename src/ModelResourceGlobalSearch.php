<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ModelResourceGlobalSearch
{
    private const string CONTAINER_KEY = 'prince.global_search';

    /**
     * Called automatically by ModelResource::routes() for resources with globalSearch: true.
     * Accumulates search metadata in the app container for the current app lifecycle.
     *
     * @param array{table: string, routePrefix: string, schema: list<ColumnSchema>} $searchData
     */
    public static function register(array $searchData): void
    {
        /** @var list<array{table: string, routePrefix: string, schema: list<ColumnSchema>}> $existing */
        $existing = app()->bound(self::CONTAINER_KEY) ? app(self::CONTAINER_KEY) : [];

        app()->instance(self::CONTAINER_KEY, [...$existing, $searchData]);
    }

    /**
     * Registers GET /search reading all resources that called routes() with globalSearch: true.
     *
     * Returns { data: [{ id, description, resource, link }] }.
     * Accepts ?q=value — same operator syntax as column filters:
     *   %foo%  → LIKE on text/enum columns
     *   foo    → exact match on text/enum columns
     * Numeric and datetime columns are always skipped.
     * Each record appears at most once per resource regardless of how many text columns match.
     */
    public static function routes(): void
    {
        /** @var list<array{table: string, routePrefix: string, schema: list<ColumnSchema>}> $entries */
        $entries = app()->bound(self::CONTAINER_KEY) ? app(self::CONTAINER_KEY) : [];

        Route::get('/search', function (Request $request) use ($entries) {
            $q = $request->query('q');

            if (!is_string($q) || $q === '') {
                return response()->json(['data' => []]);
            }

            $unions = [];
            $bindings = [];

            foreach ($entries as ['table' => $table, 'routePrefix' => $prefix, 'schema' => $schema]) {
                $linkExpr = self::linkSql($prefix);

                // Collect matchable columns for this table: [name, operator, binding_value]
                $columns = [];

                foreach ($schema as $column) {
                    if (in_array($column->type, ['integer', 'number', 'datetime'], true)) {
                        continue;
                    }

                    try {
                        [$operator, $value] = ModelResourceQuery::parseFilter($column, $q);
                    } catch (BadRequestHttpException) {
                        continue;
                    }

                    $columns[] = [
                        'name' => $column->name,
                        'operator' => $operator,
                        'value' => $value instanceof BackedEnum ? $value->value : $value,
                    ];
                }

                if ($columns === []) {
                    continue;
                }

                // One SELECT per table so each record appears at most once even when
                // multiple text columns match. The CASE picks the first matching column
                // as the description; the WHERE filters to rows where any column matches.
                //
                // CASE WHEN col1 op ? THEN col1 WHEN col2 op ? THEN col2 END AS description
                // WHERE col1 op ? OR col2 op ?
                //
                // Bindings order: all CASE values first, then all WHERE values.
                $caseWhen = 'CASE';

                foreach ($columns as ['name' => $name, 'operator' => $op]) {
                    $caseWhen .= " WHEN `{$name}` {$op} ? THEN `{$name}`";
                }

                $caseWhen .= ' END';

                $where = implode(' OR ', array_map(
                    fn(array $col) => "`{$col['name']}` {$col['operator']} ?",
                    $columns,
                ));

                $unions[] = "SELECT id, {$caseWhen} AS description, '{$table}' AS resource, {$linkExpr} AS link"
                    . " FROM `{$table}` WHERE {$where}";

                foreach ($columns as ['value' => $val]) {
                    $bindings[] = $val; // for CASE
                }

                foreach ($columns as ['value' => $val]) {
                    $bindings[] = $val; // for WHERE
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

<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

readonly class ModelResourceGlobalSearch
{
    /**
     * @param list<array{table: string, routePrefix: string, connection: string|null, schema: Closure(): list<ColumnSchema>}> $entries
     */
    private function __construct(private array $entries) {}

    /**
     * @param list<array{table: string, routePrefix: string, connection: string|null, schema: Closure(): list<ColumnSchema>}> $entries
     */
    public static function of(array $entries): self
    {
        return new self($entries);
    }

    /**
     * Registers GET /search returning { data: [{ id, description, resource, link }] }.
     *
     * Accepts ?q=value — same operator syntax as column filters:
     *   %foo%  → LIKE on text/enum columns
     *   foo    → exact match on text/enum columns
     * Numeric and datetime columns are always skipped.
     * Each record appears at most once per resource regardless of how many text columns match.
     */
    public function routes(): void
    {
        $entries = $this->entries;

        // Capture the outer Route::prefix() group at registration time so links
        // include any base path (e.g. "/api/backoffice/invoices/1" instead of "/invoices/1").
        $groupPrefix = trim(Route::getLastGroupPrefix(), '/');

        Route::get('/search', function (Request $request) use ($entries, $groupPrefix) {
            $q = $request->query('q');

            if (!is_string($q) || $q === '') {
                return response()->json(['data' => []]);
            }

            // Group entries by connection so each UNION runs on the correct database.
            /** @var array<string, list<array{table: string, routePrefix: string, connection: string|null, schema: Closure(): list<ColumnSchema>}>> $grouped */
            $grouped = [];

            foreach ($entries as $entry) {
                $connKey = $entry['connection'] ?? '_default';
                $grouped[$connKey][] = $entry;
            }

            $allResults = [];

            foreach ($grouped as $connKey => $connEntries) {
                $connection = $connKey === '_default' ? null : $connKey;
                $db = DB::connection($connection);
                $driverName = $db->getDriverName();

                $unions = [];
                $bindings = [];

                foreach ($connEntries as ['table' => $table, 'routePrefix' => $prefix, 'schema' => $schemaResolver]) {
                    $fullPrefix = $groupPrefix !== '' ? "{$groupPrefix}/{$prefix}" : $prefix;
                    $linkExpr = self::linkSql($fullPrefix, $driverName);

                    // Collect matchable columns for this table
                    $columns = [];

                    foreach ($schemaResolver() as $column) {
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
                    $caseWhen = 'CASE';

                    foreach ($columns as ['name' => $name, 'operator' => $op]) {
                        $qi = self::quoteIdentifier($name, $driverName);
                        $caseWhen .= " WHEN {$qi} {$op} ? THEN {$qi}";
                    }

                    $caseWhen .= ' END';

                    $where = implode(' OR ', array_map(
                        fn(array $col) => self::quoteIdentifier($col['name'], $driverName) . " {$col['operator']} ?",
                        $columns,
                    ));

                    $unions[] = 'SELECT ' . self::quoteIdentifier('id', $driverName) . ", {$caseWhen} AS description, '{$table}' AS resource, {$linkExpr} AS link"
                        . ' FROM ' . self::quoteIdentifier($table, $driverName) . " WHERE {$where}";

                    foreach ($columns as ['value' => $val]) {
                        $bindings[] = $val; // for CASE
                    }

                    foreach ($columns as ['value' => $val]) {
                        $bindings[] = $val; // for WHERE
                    }
                }

                if ($unions === []) {
                    continue;
                }

                $sql = implode(' UNION ', $unions);
                $results = $db->select($sql, $bindings);
                array_push($allResults, ...$results);
            }

            return response()->json(['data' => $allResults]);
        });
    }

    /**
     * Returns the SQL expression for building the resource link column, adapting to the DB driver.
     */
    private static function linkSql(string $routePrefix, string $driverName): string
    {
        return match ($driverName) {
            'sqlite' => "'/{$routePrefix}/' || CAST(id AS TEXT)",
            default => "CONCAT('/{$routePrefix}/', id)",
        };
    }

    /**
     * Quotes an identifier (table or column name) using the correct style for the active DB driver.
     * MySQL/SQLite use backticks; PostgreSQL uses double-quotes.
     */
    private static function quoteIdentifier(string $name, string $driverName): string
    {
        return match ($driverName) {
            'pgsql' => '"' . str_replace('"', '""', $name) . '"',
            default => '`' . str_replace('`', '``', $name) . '`',
        };
    }
}

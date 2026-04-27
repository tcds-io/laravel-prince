<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Support\Facades\Route;

readonly class ModelResourceGlobalSchema
{
    /**
     * @param list<array{table: string, schema: Closure(): list<ColumnSchema>, resources: list<string>, resourcePermissions: array{read?: string, create?: string, update?: string, delete?: string}, actions: list<ResourceAction>, authorizer: Closure}> $entries
     */
    private function __construct(private array $entries) {}

    /**
     * @param list<array{table: string, schema: Closure(): list<ColumnSchema>, resources: list<string>, resourcePermissions: array{read?: string, create?: string, update?: string, delete?: string}, actions: list<ResourceAction>, authorizer: Closure}> $entries
     */
    public static function of(array $entries): self
    {
        return new self($entries);
    }

    /**
     * Registers GET /_schema returning { data: [ { resource, schema, resources, permissions }, ... ] }
     * for every registered resource. Always accessible regardless of permissions.
     */
    public function routes(): void
    {
        $entries = $this->entries;

        Route::get('/_schema', function () use ($entries) {
            $data = array_map(function (array $entry) {
                return [
                    'resource'    => $entry['table'],
                    'resources'   => $entry['resources'],
                    'schema'      => ($entry['schema'])(),
                    'permissions' => ModelResource::buildPermissionsMap(
                        $entry['table'],
                        $entry['resourcePermissions'],
                        $entry['actions'],
                        $entry['authorizer'],
                    ),
                ];
            }, $entries);

            return response()->json(['data' => $data]);
        });
    }
}

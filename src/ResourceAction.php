<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;

/**
 * Describes a single custom route attached to a resource.
 *
 * Use the named constructors to create instances:
 *
 *   ResourceAction::post('/import',   fn(...) => ..., permission: 'invoices:import')
 *   ResourceAction::post('/{id}/send', fn(Invoice $i) => ..., permission: 'invoices:send')
 *
 * Paths containing {id} are item-level actions — the matching record is resolved and
 * injected into the callback automatically (404 if not found). All other paths are
 * collection-level actions.
 */
readonly class ResourceAction
{
    private function __construct(
        public string $method,
        public string $path,
        public Closure|string $action,
        public ?string $permission = null,
    ) {}

    public static function get(string $path, Closure|string $action, ?string $permission = null): self
    {
        return new self('GET', $path, $action, $permission);
    }

    public static function post(string $path, Closure|string $action, ?string $permission = null): self
    {
        return new self('POST', $path, $action, $permission);
    }

    public static function put(string $path, Closure|string $action, ?string $permission = null): self
    {
        return new self('PUT', $path, $action, $permission);
    }

    public static function patch(string $path, Closure|string $action, ?string $permission = null): self
    {
        return new self('PATCH', $path, $action, $permission);
    }

    public static function delete(string $path, Closure|string $action, ?string $permission = null): self
    {
        return new self('DELETE', $path, $action, $permission);
    }

    /**
     * Returns true when the path contains {id}, meaning the action operates on a single record.
     */
    public function isItemAction(): bool
    {
        return str_contains($this->path, '{id}');
    }
}

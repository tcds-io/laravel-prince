<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

/**
 * Describes a single custom route attached to a resource.
 *
 * Use the named constructors to create instances:
 *
 *   ResourceAction::post('/import',    ImportInvoicesAction::class, permission: 'invoices:import')
 *   ResourceAction::post('/{id}/send', SendInvoiceAction::class,    permission: 'invoices:send')
 *
 * The action must be an invokable class (a class with __invoke). Laravel's IoC container
 * resolves and calls it, so any type-hinted dependencies — Request, services, or the model
 * instance for item actions — are injected automatically.
 *
 * Paths containing {id} are item-level actions — the matching record is resolved and
 * injected into the action automatically (404 if not found). All other paths are
 * collection-level actions.
 */
readonly class ResourceAction
{
    /**
     * @param class-string $action Invokable controller class
     */
    private function __construct(
        public string $method,
        public string $path,
        public string $action,
        public ?string $permission = null,
    ) {}

    /** @param class-string $action */
    public static function get(string $path, string $action, ?string $permission = null): self
    {
        return new self('GET', $path, $action, $permission);
    }

    /** @param class-string $action */
    public static function post(string $path, string $action, ?string $permission = null): self
    {
        return new self('POST', $path, $action, $permission);
    }

    /** @param class-string $action */
    public static function put(string $path, string $action, ?string $permission = null): self
    {
        return new self('PUT', $path, $action, $permission);
    }

    /** @param class-string $action */
    public static function patch(string $path, string $action, ?string $permission = null): self
    {
        return new self('PATCH', $path, $action, $permission);
    }

    /** @param class-string $action */
    public static function delete(string $path, string $action, ?string $permission = null): self
    {
        return new self('DELETE', $path, $action, $permission);
    }
}

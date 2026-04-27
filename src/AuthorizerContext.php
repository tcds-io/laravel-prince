<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

/**
 * Carries the context of the current request when the authorizer closure is invoked.
 * Declare it as a parameter in your authorizer to receive it — it is optional.
 *
 * Example:
 *   ->authorizer(fn(RequestContext $ctx) => in_array($ctx->permission, $user->permissions()))
 *   ->authorizer(fn(RequestContext $ctx, AuthService $auth) => $auth->can($ctx->permission))
 *   ->authorizer(fn(AuthService $auth) => $auth->isAdmin())   // RequestContext not needed
 */
readonly class AuthorizerContext
{
    public function __construct(
        /** HTTP method of the incoming request (GET, POST, PATCH, DELETE, …) */
        public string $method,
        /** Path of the incoming request (e.g. /invoices or /invoices/1) */
        public string $path,
        /** The permission string being checked for this route */
        public string $permission,
    ) {}
}

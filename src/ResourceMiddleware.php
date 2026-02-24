<?php

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class ResourceMiddleware
{
    /**
     * @param list<string> $userPermissions
     */
    private function __construct(private string $action, private array $userPermissions)
    {
    }

    /**
     * @param list<string> $userPermissions
     */
    public static function of(string $action, array $userPermissions): self
    {
        return new self($action, $userPermissions);
    }

    public function __toString(): string
    {
        $key = 'laravel_model_api_middleware_' . spl_object_id($this);
        app()->instance($key, $this);

        return $key;
    }

    /**
     * @param Closure(Request $request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($this->action, $this->userPermissions)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}

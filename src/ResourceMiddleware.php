<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

readonly class ResourceMiddleware
{
    private string $key;

    /**
     * @param Closure $authorizer
     */
    private function __construct(private string $permission, private Closure $authorizer)
    {
        $this->key = 'prince_middleware_' . uniqid('', true);
    }

    /**
     * @param Closure $authorizer
     */
    public static function of(string $permission, Closure $authorizer): self
    {
        return new self($permission, $authorizer);
    }

    public function __toString(): string
    {
        app()->instance($this->key, $this);

        return $this->key;
    }

    /**
     * @param Closure(Request $request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->permission !== 'public') {
            $context = new AuthorizerContext($request->method(), $request->getPathInfo(), $this->permission);
            // RequestContext is bound as an optional injectable; closures that don't
            // declare it (e.g. fn(AuthService $a) => ...) simply won't receive it.
            if (!app()->call($this->authorizer, [AuthorizerContext::class => $context])) {
                throw new AccessDeniedHttpException();
            }
        }

        return $next($request);
    }
}

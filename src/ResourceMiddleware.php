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
     * @param Closure(): list<string> $userPermissions
     */
    private function __construct(private string $action, private Closure $userPermissions)
    {
        $this->key = 'prince_middleware_' . uniqid('', true);
    }

    /**
     * @param Closure(): list<string> $userPermissions
     */
    public static function of(string $action, Closure $userPermissions): self
    {
        return new self($action, $userPermissions);
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
        if ($this->action !== 'public' && !in_array($this->action, ($this->userPermissions)())) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}

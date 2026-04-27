<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Unit;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tcds\Io\Prince\AuthorizerContext;
use Tcds\Io\Prince\ResourceMiddleware;

class ResourceMiddlewareTest extends TestCase
{
    #[Test]
    public function handle_calls_next_when_authorizer_returns_true(): void
    {
        $middleware = ResourceMiddleware::of('model:create', fn(AuthorizerContext $context) => in_array($context->permission, ['model:list', 'model:create', 'model:delete']));
        $request = Request::create('/', 'POST');
        $response = new Response('ok', 200);

        $result = $middleware->handle($request, fn() => $response);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function handle_throws_when_authorizer_returns_false(): void
    {
        $middleware = ResourceMiddleware::of('model:delete', fn(AuthorizerContext $context) => in_array($context->permission, ['model:list', 'model:get']));
        $request = Request::create('/', 'DELETE');

        $this->expectException(AccessDeniedHttpException::class);

        $middleware->handle($request, fn() => new Response());
    }

    #[Test]
    public function handle_throws_when_authorizer_always_returns_false(): void
    {
        $middleware = ResourceMiddleware::of('model:list', fn() => false);
        $request = Request::create('/', 'GET');

        $this->expectException(AccessDeniedHttpException::class);

        $middleware->handle($request, fn() => new Response());
    }

    #[Test]
    public function handle_passes_the_original_request_to_next(): void
    {
        $middleware = ResourceMiddleware::of('model:get', fn(AuthorizerContext $context) => $context->permission === 'model:get');
        $request = Request::create('/', 'GET');
        $capturedRequest = null;

        $middleware->handle($request, function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response();
        });

        $this->assertSame($request, $capturedRequest);
    }

    #[Test]
    public function handle_injects_request_context_with_correct_values(): void
    {
        $capturedContext = null;

        $middleware = ResourceMiddleware::of('model:read', function (AuthorizerContext $context) use (&$capturedContext) {
            $capturedContext = $context;

            return true;
        });

        $request = Request::create('/invoices', 'GET');
        $middleware->handle($request, fn() => new Response());

        $this->assertNotNull($capturedContext);
        $this->assertSame('GET', $capturedContext->method);
        $this->assertSame('/invoices', $capturedContext->path);
        $this->assertSame('model:read', $capturedContext->permission);
    }

    #[Test]
    public function handle_skips_authorizer_when_permission_is_public(): void
    {
        $authorizerCalled = false;

        $middleware = ResourceMiddleware::of('public', function () use (&$authorizerCalled) {
            $authorizerCalled = true;

            return false; // would deny if called
        });

        $request = Request::create('/', 'GET');
        $response = new Response('ok', 200);

        $result = $middleware->handle($request, fn() => $response);

        $this->assertFalse($authorizerCalled);
        $this->assertSame($response, $result);
    }

    #[Test]
    public function handle_supports_authorizer_without_request_context_parameter(): void
    {
        // Authorizer doesn't declare RequestContext — IoC resolves it without it
        $middleware = ResourceMiddleware::of('model:admin', fn() => true);
        $request = Request::create('/', 'POST');
        $response = new Response('ok', 200);

        $result = $middleware->handle($request, fn() => $response);

        $this->assertSame($response, $result);
    }
}

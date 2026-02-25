<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tcds\Io\Prince\ResourceMiddleware;

class ResourceMiddlewareTest extends TestCase
{
    #[Test]
    public function handle_calls_next_when_action_is_in_user_permissions(): void
    {
        $middleware = ResourceMiddleware::of('model:create', ['model:list', 'model:create', 'model:delete']);
        $request = Request::create('/', 'POST');
        $response = new Response('ok', 200);

        $result = $middleware->handle($request, fn() => $response);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function handle_throws_when_action_is_not_in_user_permissions(): void
    {
        $middleware = ResourceMiddleware::of('model:delete', ['model:list', 'model:get']);
        $request = Request::create('/', 'DELETE');

        $this->expectException(AccessDeniedHttpException::class);

        $middleware->handle($request, fn() => new Response());
    }

    #[Test]
    public function handle_throws_when_user_has_no_permissions_at_all(): void
    {
        $middleware = ResourceMiddleware::of('model:list', []);
        $request = Request::create('/', 'GET');

        $this->expectException(AccessDeniedHttpException::class);

        $middleware->handle($request, fn() => new Response());
    }

    #[Test]
    public function handle_passes_the_original_request_to_next(): void
    {
        $middleware = ResourceMiddleware::of('model:get', ['model:get']);
        $request = Request::create('/', 'GET');
        $capturedRequest = null;

        $middleware->handle($request, function (Request $req) use (&$capturedRequest) {
            $capturedRequest = $req;

            return new Response();
        });

        $this->assertSame($request, $capturedRequest);
    }
}

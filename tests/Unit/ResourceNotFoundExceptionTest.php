<?php

namespace Test\Tcds\Io\Prince\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tcds\Io\Prince\ResourceNotFoundException;

class ResourceNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function message_contains_the_given_id(): void
    {
        $exception = new ResourceNotFoundException(42);

        $result = $exception->getMessage();

        $this->assertSame('Resource #42 not found', $result);
    }

    #[Test]
    public function is_a_not_found_http_exception(): void
    {
        $exception = new ResourceNotFoundException(1);

        $this->assertInstanceOf(NotFoundHttpException::class, $exception);
    }
}

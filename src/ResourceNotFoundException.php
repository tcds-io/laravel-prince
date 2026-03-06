<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResourceNotFoundException extends NotFoundHttpException
{
    public function __construct(int|string $id)
    {
        parent::__construct("Resource #$id not found");
    }
}

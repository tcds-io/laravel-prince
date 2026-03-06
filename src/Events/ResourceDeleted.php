<?php

declare(strict_types=1);

namespace Tcds\Io\Prince\Events;

readonly class ResourceDeleted
{
    public function __construct(
        public int|string $modelId,
    ) {}
}

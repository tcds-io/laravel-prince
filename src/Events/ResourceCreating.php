<?php

declare(strict_types=1);

namespace Tcds\Io\Prince\Events;

class ResourceCreating implements MutableDataEvent
{
    /**
     * @param class-string $modelName
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $modelName,
        public array $data,
    ) {}
}

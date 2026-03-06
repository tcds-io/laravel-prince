<?php

declare(strict_types=1);

namespace Tcds\Io\Prince\Events;

use Illuminate\Database\Eloquent\Model;

class ResourceUpdating implements MutableDataEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly Model $model,
        public array $data,
    ) {}
}

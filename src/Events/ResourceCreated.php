<?php

declare(strict_types=1);

namespace Tcds\Io\Prince\Events;

use Illuminate\Database\Eloquent\Model;

readonly class ResourceCreated
{
    public function __construct(
        public Model $model,
    ) {}
}

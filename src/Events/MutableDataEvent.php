<?php

declare(strict_types=1);

namespace Tcds\Io\Prince\Events;

interface MutableDataEvent
{
    /** @var array<string, mixed> */
    public array $data { get; }
}

<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

class Prince
{
    /**
     * @return array{ read: string, create: string, update: string, delete: string }
     */
    public static function permissions(string $context, string $resource): array
    {
        return [
            'read' => "$context:$resource.read",
            'create' => "$context:$resource.create",
            'update' => "$context:$resource.update",
            'delete' => "$context:$resource.delete",
        ];
    }
}

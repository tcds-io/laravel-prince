<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

class Prince
{
    /**
     * @return array{ list: string, get: string, create: string, update: string, delete: string }
     */
    public static function permissions(string $context, string $resource): array
    {
        return [
            'list' => "$context:$resource.list",
            'get' => "$context:$resource.get",
            'create' => "$context:$resource.create",
            'update' => "$context:$resource.update",
            'delete' => "$context:$resource.delete",
        ];
    }
}

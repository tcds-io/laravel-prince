<?php

declare(strict_types=1);

namespace Tcds\Io\Prince;

class Prince
{
    /**
     * @return array{ read: string, create: string, update: string, delete: string }
     */
    public static function crud(string $context, string $resource): array
    {
        return [
            'read' => "$context:$resource.read",
            'create' => "$context:$resource.create",
            'update' => "$context:$resource.update",
            'delete' => "$context:$resource.delete",
        ];
    }

    /**
     * @return array{ read: string }
     */
    public static function readOnly(string $context, string $resource): array
    {
        return [
            'read' => "$context:$resource.read",
        ];
    }

    /**
     * @return array{ read: string, create: string, update: string }
     */
    public static function readWrite(string $context, string $resource): array
    {
        return [
            'read' => "$context:$resource.read",
            'create' => "$context:$resource.create",
            'update' => "$context:$resource.update",
        ];
    }

    /**
     * @param list<'read' | 'create' | 'update' | 'delete'> $keys
     * @return array<'read'|'create'|'update'|'delete', string>
     */
    public static function without(string $context, string $resource, array $keys): array
    {
        return array_diff_key(self::crud($context, $resource), array_flip($keys));
    }
}

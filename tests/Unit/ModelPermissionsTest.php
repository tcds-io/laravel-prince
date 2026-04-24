<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Jackson\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tcds\Io\Prince\Prince;

class ModelPermissionsTest extends TestCase
{
    #[Test]
    public function get_permissions(): void
    {
        $this->assertEquals(
            [
                'list' => 'finance:invoice.list',
                'get' => 'finance:invoice.get',
                'create' => 'finance:invoice.create',
                'update' => 'finance:invoice.update',
                'delete' => 'finance:invoice.delete',
            ],
            Prince::permissions('finance', 'invoice'),
        );
    }
}

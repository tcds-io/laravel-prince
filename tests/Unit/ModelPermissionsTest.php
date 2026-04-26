<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Jackson\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tcds\Io\Prince\Prince;

class ModelPermissionsTest extends TestCase
{
    #[Test]
    public function get_all_permissions(): void
    {
        $this->assertEquals(
            [
                'read' => 'finance:invoice.read',
                'create' => 'finance:invoice.create',
                'update' => 'finance:invoice.update',
                'delete' => 'finance:invoice.delete',
            ],
            Prince::all('finance', 'invoice'),
        );
    }

    #[Test]
    public function get_read_only_permissions(): void
    {
        $this->assertEquals(
            [
                'read' => 'finance:invoice.read',
            ],
            Prince::readOnly('finance', 'invoice'),
        );
    }

    #[Test]
    public function get_read_write_permissions(): void
    {
        $this->assertEquals(
            [
                'read' => 'finance:invoice.read',
                'create' => 'finance:invoice.create',
                'update' => 'finance:invoice.update',
            ],
            Prince::readWrite('finance', 'invoice'),
        );
    }

    // --- without ---

    #[Test]
    public function without_excludes_the_given_keys(): void
    {
        $this->assertEquals(
            [
                'read'   => 'finance:invoice.read',
                'create' => 'finance:invoice.create',
                'update' => 'finance:invoice.update',
            ],
            Prince::without('finance', 'invoice', ['delete']),
        );
    }

    #[Test]
    public function without_excludes_multiple_keys(): void
    {
        $this->assertEquals(
            [
                'read' => 'finance:invoice.read',
            ],
            Prince::without('finance', 'invoice', ['create', 'update', 'delete']),
        );
    }

    #[Test]
    public function without_with_empty_list_returns_all_permissions(): void
    {
        $this->assertEquals(
            Prince::all('finance', 'invoice'),
            Prince::without('finance', 'invoice', []),
        );
    }

    #[Test]
    public function without_all_keys_returns_empty_array(): void
    {
        $this->assertEquals(
            [],
            Prince::without('finance', 'invoice', ['read', 'create', 'update', 'delete']),
        );
    }
}

<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Stubs\Actions;

use Illuminate\Http\JsonResponse;

class ExportInvoicesAction
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['exported' => true]);
    }
}

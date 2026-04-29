<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Stubs\Actions;

use Illuminate\Http\JsonResponse;

class InvoiceIdAction
{
    public function __invoke(string $id): JsonResponse
    {
        return response()->json(['id' => $id]);
    }
}

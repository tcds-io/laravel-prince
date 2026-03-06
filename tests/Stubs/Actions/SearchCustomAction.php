<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Stubs\Actions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchCustomAction
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['q' => $request->query('q')]);
    }
}

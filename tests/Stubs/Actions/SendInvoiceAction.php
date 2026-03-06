<?php

declare(strict_types=1);

namespace Test\Tcds\Io\Prince\Stubs\Actions;

use Illuminate\Http\JsonResponse;
use Test\Tcds\Io\Prince\Feature\TestInvoice;

class SendInvoiceAction
{
    public function __invoke(TestInvoice $invoice): JsonResponse
    {
        return response()->json(['sent' => $invoice->id]);
    }
}

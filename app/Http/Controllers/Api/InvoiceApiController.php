<?php

namespace App\Http\Controllers\Api;

use App\Actions\GetInvoiceDetails;
use App\Actions\ListInvoicesApi;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\Api\InvoiceApiResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceApiController extends Controller
{
    public function index(Request $request, ListInvoicesApi $listInvoices): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.view'), 403);

        return response()->json($listInvoices->handle($request, $request->integer('per_page', 20)));
    }

    public function show(Request $request, string $invoice, GetInvoiceDetails $getDetails, InvoiceApiResource $resource): JsonResponse
    {
        abort_unless($request->user()?->can('billing.invoices.view'), 403);
        $model = Invoice::query()->where('public_id', $invoice)->firstOrFail();

        return response()->json($resource->make($getDetails->handle($model)));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordCollectorPaymentBatch;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CollectorPaymentController extends Controller
{
    public function store(Request $request, RecordCollectorPaymentBatch $batch): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $items = $request->input('items');
        abort_unless(is_array($items) && count($items) <= 100, 422, 'items must contain between one and one hundred payments.');

        return response()->json($batch->handle(array_values($items), $request->user()));
    }
}

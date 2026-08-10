<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateWhishPaymentAttempt;
use App\Actions\RecordCollectorPaymentBatch;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Support\QrCodeRenderer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CollectorPaymentController extends Controller
{
    public function store(Request $request, RecordCollectorPaymentBatch $batch): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $items = $request->input('items');
        abort_unless(is_array($items) && count($items) <= 100, 422, 'items must contain between one and one hundred payments.');

        return response()->json($batch->handle(array_values($items), $request->user()));
    }

    public function createWhish(Request $request, CreateWhishPaymentAttempt $create, QrCodeRenderer $qr): JsonResponse
    {
        abort_unless($request->user()?->can('payments.collect'), 403);
        $validated = $request->validate([
            'customer_id' => ['required', 'string'],
            'invoice_id' => ['nullable', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['USD', 'LBP', 'AED'])],
        ]);
        $customer = Customer::query()->where('public_id', $validated['customer_id'])->firstOrFail();
        $invoice = isset($validated['invoice_id'])
            ? Invoice::query()->where('public_id', $validated['invoice_id'])->where('customer_id', $customer->id)->firstOrFail()
            : null;
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        try {
            $attempt = $create->handle(
                $actor,
                $customer,
                (int) $validated['amount'],
                (string) $validated['currency'],
                $invoice,
                (string) $request->header('X-Idempotency-Key'),
            );
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        abort_if($attempt->collect_url === null, 502, 'Whish did not return a collection URL.');

        return response()->json([
            'data' => [
                'id' => $attempt->public_id,
                'gateway' => $attempt->gateway,
                'status' => $attempt->status->value,
                'external_id' => $attempt->external_id,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'collect_url' => $attempt->collect_url,
                'qr_code' => ['format' => 'svg', 'data_uri' => $qr->dataUri($attempt->collect_url)],
                'customer_id' => $customer->public_id,
                'invoice_id' => $invoice?->public_id,
            ],
        ], $attempt->wasRecentlyCreated ? 201 : 200);
    }
}

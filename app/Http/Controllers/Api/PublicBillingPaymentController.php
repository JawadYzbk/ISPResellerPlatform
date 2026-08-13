<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreatePortalPaymentIntent;
use App\Actions\CreateWhishPaymentAttempt;
use App\Actions\ResolvePublicBillingLink;
use App\Actions\SettleWhishPaymentAttempt;
use App\Enums\PaymentAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Support\QrCodeRenderer;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicBillingPaymentController extends Controller
{
    public function stripe(Request $request, string $token, ResolvePublicBillingLink $resolve, CreatePortalPaymentIntent $create): JsonResponse
    {
        $link = $resolve->handle($token, false);
        $this->payable($link->type, $link->invoice_id);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $invoice = $link->invoice;
        abort_unless($invoice !== null, 404);
        try {
            $intent = $create->handle($link->customer, $invoice, $validated['amount'], $this->idempotencyKey($request));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'id' => $intent->id,
            'status' => $intent->status,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'payload' => $intent->payload,
        ], 201);
    }

    public function whish(Request $request, string $token, ResolvePublicBillingLink $resolve, CreateWhishPaymentAttempt $create, QrCodeRenderer $qr): JsonResponse
    {
        $link = $resolve->handle($token, false);
        $this->payable($link->type, $link->invoice_id);
        $validated = $request->validate(['amount' => ['required', 'integer', 'min:1']]);
        $invoice = $link->invoice;
        abort_unless($invoice !== null, 404);
        try {
            $attempt = $create->handle(null, $link->customer, $validated['amount'], $invoice->currency, $invoice, $this->idempotencyKey($request), 'public_link');
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        abort_if($attempt->collect_url === null, 502, 'Whish did not return a collection URL.');

        return response()->json([
            'attempt_id' => $attempt->public_id,
            'status' => $attempt->status->value,
            'collect_url' => $attempt->collect_url,
            'qr_data_uri' => $qr->dataUri($attempt->collect_url),
        ], 201);
    }

    public function whishStatus(string $token, string $attempt, ResolvePublicBillingLink $resolve, SettleWhishPaymentAttempt $settle): JsonResponse
    {
        $link = $resolve->handle($token, false);
        $this->payable($link->type, $link->invoice_id);
        $model = PaymentAttempt::query()
            ->where('public_id', $attempt)
            ->where('gateway', 'whish')
            ->where('invoice_id', $link->invoice_id)
            ->where('customer_id', $link->customer_id)
            ->firstOrFail();
        try {
            $model = $settle->handle($model);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'status' => $model->status->value,
            'terminal' => in_array($model->status, [PaymentAttemptStatus::Succeeded, PaymentAttemptStatus::Failed, PaymentAttemptStatus::SettlementFailed], true),
            'payment_id' => $model->payment?->public_id,
        ]);
    }

    private function payable(string $type, ?int $invoiceId): void
    {
        abort_unless($type === 'payment' && $invoiceId !== null, 404);
    }

    private function idempotencyKey(Request $request): string
    {
        $key = trim((string) $request->header('X-Idempotency-Key'));
        abort_if($key === '', 422, 'An idempotency key is required.');

        return $key;
    }
}

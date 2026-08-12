<?php

namespace App\Http\Controllers\Api;

use App\Actions\SettleWhishPaymentAttempt;
use App\Enums\PaymentAttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WhishPaymentCallbackController extends Controller
{
    public function success(Request $request, SettleWhishPaymentAttempt $settle): JsonResponse
    {
        return $this->handle($request, $settle);
    }

    public function failure(Request $request, SettleWhishPaymentAttempt $settle): JsonResponse
    {
        return $this->handle($request, $settle);
    }

    private function handle(Request $request, SettleWhishPaymentAttempt $settle): JsonResponse
    {
        $externalId = (string) ($request->query('externalId') ?? $request->query('external_id') ?? '');
        $currency = strtoupper((string) ($request->query('currency') ?? ''));
        if (preg_match('/^[1-9][0-9]*$/', $externalId) !== 1) {
            return response()->json(['message' => 'A valid Whish external ID is required.'], 422);
        }

        $attempt = PaymentAttempt::withoutGlobalScopes()
            ->where('gateway', 'whish')
            ->where('external_id', $externalId)
            ->first();
        if (! $attempt instanceof PaymentAttempt) {
            return response()->json(['message' => 'Whish payment attempt not found.'], 404);
        }
        if ($currency !== '' && strtoupper($attempt->currency) !== $currency) {
            return response()->json(['message' => 'Whish callback currency does not match the payment attempt.'], 422);
        }

        try {
            $attempt = $settle->handle($attempt);
        } catch (\DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        } catch (\Throwable) {
            return response()->json(['message' => 'Whish payment status could not be verified.'], 502);
        }

        $httpStatus = match ($attempt->status) {
            PaymentAttemptStatus::Succeeded => 200,
            PaymentAttemptStatus::Pending => 202,
            PaymentAttemptStatus::SettlementFailed => 500,
            PaymentAttemptStatus::Failed => 422,
        };

        return response()->json(['data' => [
            'id' => $attempt->public_id,
            'status' => $attempt->status->value,
            'external_id' => $attempt->external_id,
            'payment_id' => $attempt->payment?->public_id,
            'provider_transaction_id' => $attempt->provider_transaction_id,
        ]], $httpStatus);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Actions\ProcessPaymentGatewayWebhook;
use App\Domain\Payments\InvalidPaymentWebhookPayload;
use App\Domain\Payments\InvalidPaymentWebhookSignature;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentGatewayWebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, ProcessPaymentGatewayWebhook $process): JsonResponse
    {
        try {
            return response()->json($process->handle($gateway, $request->getContent(), $request->header('Stripe-Signature')));
        } catch (InvalidPaymentWebhookSignature $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        } catch (InvalidPaymentWebhookPayload|DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}

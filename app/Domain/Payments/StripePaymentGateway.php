<?php

namespace App\Domain\Payments;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Facades\Http;

final class StripePaymentGateway implements PaymentGateway
{
    public function createIntent(Customer $customer, Invoice $invoice, int $amount, string $currency, string $idempotencyKey): PaymentIntentResult
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || trim($secret) === '') {
            throw new DomainException('Stripe is not configured for online payments.');
        }

        $tenantPublicId = Tenant::query()->whereKey($customer->tenant_id)->value('public_id');
        if (! is_string($tenantPublicId) || $tenantPublicId === '') {
            throw new DomainException('The customer tenant could not be resolved for Stripe payment metadata.');
        }

        $endpoint = rtrim((string) config('services.stripe.endpoint', 'https://api.stripe.com'), '/').'/v1/payment_intents';
        $response = Http::withBasicAuth($secret, '')
            ->asForm()
            ->acceptJson()
            ->timeout((int) config('services.stripe.timeout', 15))
            ->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->post($endpoint, [
                'amount' => $amount,
                'currency' => strtolower($currency),
                'automatic_payment_methods[enabled]' => 'true',
                'metadata[tenant_public_id]' => $tenantPublicId,
                'metadata[customer_public_id]' => (string) $customer->public_id,
                'metadata[invoice_public_id]' => (string) $invoice->public_id,
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message');
            $message = is_string($message) && $message !== '' ? $message : 'The provider rejected the payment intent.';

            throw new DomainException('Stripe payment intent creation failed: '.$message);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new DomainException('Stripe returned an invalid payment intent response.');
        }

        $id = $payload['id'] ?? null;
        $status = $payload['status'] ?? null;
        $responseAmount = $payload['amount'] ?? null;
        $responseCurrency = $payload['currency'] ?? null;
        $clientSecret = $payload['client_secret'] ?? null;
        if (! is_string($id) || ! is_string($status) || ! is_int($responseAmount) || ! is_string($responseCurrency) || ! is_string($clientSecret)) {
            throw new DomainException('Stripe returned an incomplete payment intent response.');
        }

        return new PaymentIntentResult($id, $status, $responseAmount, strtoupper($responseCurrency), [
            'client_secret' => $clientSecret,
            'publishable_key' => config('services.stripe.publishable_key'),
            'payment_method_types' => is_array($payload['payment_method_types'] ?? null) ? $payload['payment_method_types'] : [],
        ]);
    }
}

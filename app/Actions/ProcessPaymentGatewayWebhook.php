<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Payments\InvalidPaymentWebhookPayload;
use App\Domain\Payments\InvalidPaymentWebhookSignature;
use App\Domain\Payments\StripeWebhookSignatureVerifier;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\Tenancy;
use DomainException;
use JsonException;

final readonly class ProcessPaymentGatewayWebhook implements Action
{
    public function __construct(private RecordPayment $recordPayment, private StripeWebhookSignatureVerifier $signatures, private Tenancy $tenancy) {}

    /** @return array{status: string, event_id: string, payment_id: string|null} */
    public function handle(string $gateway, string $rawPayload, ?string $signature): array
    {
        if ($gateway !== 'stripe') {
            throw new DomainException('Unsupported payment gateway webhook.');
        }

        $secret = config('services.stripe.webhook_secret');
        if (! is_string($secret) || ! $this->signatures->verify($rawPayload, $signature, $secret)) {
            throw new InvalidPaymentWebhookSignature('Invalid payment gateway webhook signature.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidPaymentWebhookPayload('Payment gateway webhook payload must be valid JSON.');
        }
        if (! is_array($payload)) {
            throw new InvalidPaymentWebhookPayload('Payment gateway webhook payload must be a JSON object.');
        }

        $eventId = $this->requiredString($payload['id'] ?? null, 'event id');
        $eventType = $this->requiredString($payload['type'] ?? null, 'event type');
        if ($eventType !== 'payment_intent.succeeded') {
            return ['status' => 'ignored', 'event_id' => $eventId, 'payment_id' => null];
        }

        $intent = $payload['data']['object'] ?? null;
        if (! is_array($intent)) {
            throw new InvalidPaymentWebhookPayload('Payment gateway webhook is missing its payment intent.');
        }
        $paymentIntentId = $this->requiredString($intent['id'] ?? null, 'payment intent id');
        $metadata = $intent['metadata'] ?? null;
        if (! is_array($metadata)) {
            throw new InvalidPaymentWebhookPayload('Payment intent metadata is missing.');
        }
        $tenantPublicId = $this->requiredString($metadata['tenant_public_id'] ?? null, 'tenant public id');
        $customerPublicId = $this->requiredString($metadata['customer_public_id'] ?? null, 'customer public id');
        $invoicePublicId = $this->requiredString($metadata['invoice_public_id'] ?? null, 'invoice public id');
        $currency = strtoupper($this->requiredString($intent['currency'] ?? null, 'currency'));
        $amount = $intent['amount_received'] ?? $intent['amount'] ?? null;
        if (! is_int($amount) || $amount < 1) {
            throw new InvalidPaymentWebhookPayload('Payment intent amount is invalid.');
        }

        $tenant = Tenant::query()->where('public_id', $tenantPublicId)->first();
        if (! $tenant instanceof Tenant) {
            throw new InvalidPaymentWebhookPayload('Payment intent tenant could not be found.');
        }

        return $this->tenancy->run($tenant, function () use ($eventId, $eventType, $paymentIntentId, $customerPublicId, $invoicePublicId, $currency, $amount): array {
            $customer = Customer::query()->where('public_id', $customerPublicId)->first();
            if (! $customer instanceof Customer) {
                throw new InvalidPaymentWebhookPayload('Payment intent customer could not be found.');
            }
            $invoice = Invoice::query()->where('public_id', $invoicePublicId)->where('customer_id', $customer->id)->first();
            if (! $invoice instanceof Invoice) {
                throw new InvalidPaymentWebhookPayload('Payment intent invoice could not be found.');
            }
            if (strtoupper($invoice->currency) !== $currency) {
                throw new DomainException('Payment intent currency does not match the invoice.');
            }

            $payment = $this->recordPayment->handle(
                $customer,
                $amount,
                $currency,
                'gateway',
                'gateway:stripe:'.$eventType.':'.$paymentIntentId,
                invoice: $invoice,
                reference: $paymentIntentId,
                metadata: [
                    'gateway' => 'stripe',
                    'gateway_event_id' => $eventId,
                    'gateway_payment_intent_id' => $paymentIntentId,
                ],
            );

            return ['status' => 'processed', 'event_id' => $eventId, 'payment_id' => $payment->public_id];
        });
    }

    private function requiredString(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidPaymentWebhookPayload('Payment gateway webhook '.$field.' is required.');
        }

        return trim($value);
    }
}

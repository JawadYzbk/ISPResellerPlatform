<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Payments\WhishPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;
use Throwable;

final readonly class CreateWhishPaymentAttempt implements Action
{
    public function __construct(private WhishPaymentGateway $gateway) {}

    public function handle(User $actor, Customer $customer, int $amount, string $currency, ?Invoice $invoice, string $idempotencyKey): PaymentAttempt
    {
        $currency = strtoupper(trim($currency));
        if ($amount < 1) {
            throw new DomainException('Payment amount must be positive.');
        }
        if ($customer->tenant_id !== $actor->tenant_id) {
            throw new DomainException('The customer does not belong to the active tenant.');
        }
        if ($invoice !== null && ($invoice->tenant_id !== $customer->tenant_id || $invoice->customer_id !== $customer->id)) {
            throw new DomainException('The invoice is not payable by this customer.');
        }
        if (! in_array($currency, ['USD', 'LBP', 'AED'], true) || ! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            throw new DomainException('Whish Pay supports only active USD, LBP, and AED currencies.');
        }
        if (trim($idempotencyKey) === '') {
            throw new DomainException('An idempotency key is required.');
        }

        $existing = PaymentAttempt::query()->where('gateway', 'whish')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof PaymentAttempt) {
            return $existing;
        }

        $externalId = $this->externalId();
        $attempt = PaymentAttempt::create([
            'gateway' => 'whish',
            'external_id' => $externalId,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'actor_id' => $actor->id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => PaymentAttemptStatus::Pending,
            'idempotency_key' => $idempotencyKey,
            'invoice_reference' => $invoice?->number ?: 'COL-'.$externalId,
            'metadata' => ['source' => 'collector'],
        ]);

        try {
            $response = $this->gateway->create($attempt);
            $attempt->forceFill(['collect_url' => $response->collectUrl])->save();
        } catch (DomainException $exception) {
            $attempt->forceFill(['status' => PaymentAttemptStatus::Failed, 'failure_reason' => Str::limit($exception->getMessage(), 1000, '')])->save();
            throw $exception;
        } catch (Throwable $exception) {
            $attempt->forceFill(['status' => PaymentAttemptStatus::Failed, 'failure_reason' => 'Whish payment initialization failed.'])->save();
            throw new DomainException('Whish payment initialization failed.', previous: $exception);
        }

        return $attempt->refresh();
    }

    private function externalId(): string
    {
        do {
            $externalId = (string) random_int(100_000_000, 999_999_999);
        } while (PaymentAttempt::withoutGlobalScopes()->where('gateway', 'whish')->where('external_id', $externalId)->exists());

        return $externalId;
    }
}

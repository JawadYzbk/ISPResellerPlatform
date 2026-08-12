<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Payments\WhishPaymentGateway;
use App\Enums\PaymentAttemptStatus;
use App\Models\PaymentAttempt;
use App\Support\Tenancy;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class SettleWhishPaymentAttempt implements Action
{
    public function __construct(private WhishPaymentGateway $gateway, private RecordPayment $recordPayment, private Tenancy $tenancy) {}

    public function handle(PaymentAttempt $attempt): PaymentAttempt
    {
        return $this->tenancy->run($attempt->tenant_id, function () use ($attempt): PaymentAttempt {
            $current = PaymentAttempt::query()->with(['customer', 'invoice', 'actor', 'payment'])->whereKey($attempt->id)->firstOrFail();
            if (in_array($current->status, [PaymentAttemptStatus::Succeeded, PaymentAttemptStatus::Failed, PaymentAttemptStatus::SettlementFailed], true)) {
                return $current;
            }

            $status = $this->gateway->status($current);

            return DB::transaction(function () use ($attempt, $status): PaymentAttempt {
                $current = PaymentAttempt::query()
                    ->with(['customer', 'invoice', 'actor', 'payment'])
                    ->whereKey($attempt->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if (in_array($current->status, [PaymentAttemptStatus::Succeeded, PaymentAttemptStatus::Failed, PaymentAttemptStatus::SettlementFailed], true)) {
                    return $current;
                }

                $current->forceFill([
                    'last_checked_at' => now(),
                    'provider_transaction_id' => $status->transactionId,
                ])->save();

                if ($status->collectStatus === 'pending') {
                    return $current->load('payment');
                }
                if ($status->collectStatus === 'failed') {
                    $current->forceFill([
                        'status' => PaymentAttemptStatus::Failed,
                        'failure_reason' => 'Whish reported a failed collection.',
                    ])->save();

                    return $current->load('payment');
                }
                if (! $this->gateway->matchesStatus($current, $status)) {
                    $current->forceFill([
                        'status' => PaymentAttemptStatus::Failed,
                        'failure_reason' => 'Whish status did not match the recorded amount or currency.',
                    ])->save();

                    return $current->load('payment');
                }

                try {
                    $payment = $this->recordPayment->handle(
                        $current->customer,
                        $current->amount,
                        $current->currency,
                        'mobile_wallet',
                        'gateway:whish:'.$current->external_id,
                        $current->invoice,
                        $current->actor,
                        metadata: [
                            'gateway' => 'whish',
                            'payment_attempt_id' => $current->public_id,
                            'external_id' => $current->external_id,
                            'provider_transaction_id' => $status->transactionId,
                        ],
                        reference: $status->transactionId === null ? 'Whish '.$current->external_id : 'Whish '.$status->transactionId,
                    );
                } catch (Throwable $exception) {
                    $current->forceFill([
                        'status' => PaymentAttemptStatus::SettlementFailed,
                        'failure_reason' => 'Whish confirmed the payment, but the ledger posting failed.',
                    ])->save();
                    throw new DomainException('Whish payment was confirmed but could not be posted to the ledger.', previous: $exception);
                }

                $current->forceFill([
                    'status' => PaymentAttemptStatus::Succeeded,
                    'payment_id' => $payment->id,
                    'paid_at' => now(),
                    'failure_reason' => null,
                ])->save();

                return $current->load('payment');
            });
        });
    }
}

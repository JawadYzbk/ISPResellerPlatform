<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Message;
use App\Models\Payment;
use App\Models\User;
use DomainException;

final readonly class ResendPaymentReceipt implements Action
{
    public function __construct(private QueueCustomerNotification $notify) {}

    public function handle(Payment $payment, User $actor, string $channel, string $idempotencyKey): ?Message
    {
        if ($payment->actor_id !== $actor->id || $payment->status->value !== 'posted') {
            throw new DomainException('Only your posted collector payments can have receipts resent.');
        }

        $payment->loadMissing('customer');

        return $this->notify->handle(
            $payment->customer,
            'payment.receipt',
            $idempotencyKey,
            [
                'customer_name' => $payment->customer->full_name,
                'receipt_number' => $payment->number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ],
            [$channel],
        );
    }
}

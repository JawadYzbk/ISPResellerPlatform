<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;

final readonly class GetPaymentDetails implements Action
{
    public function handle(Payment $payment): Payment
    {
        return $payment->load([
            'customer',
            'invoice',
            'cashShift',
            'actor',
            'reversalOf',
            'allocations.invoice',
        ]);
    }
}

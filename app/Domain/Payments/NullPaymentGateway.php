<?php

namespace App\Domain\Payments;

use App\Models\Customer;
use App\Models\Invoice;
use DomainException;

final class NullPaymentGateway implements PaymentGateway
{
    public function createIntent(Customer $customer, Invoice $invoice, int $amount, string $currency, string $idempotencyKey): PaymentIntentResult
    {
        throw new DomainException('No online payment gateway is configured for this tenant.');
    }
}

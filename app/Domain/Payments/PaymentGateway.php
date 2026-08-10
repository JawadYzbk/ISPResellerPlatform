<?php

namespace App\Domain\Payments;

use App\Models\Customer;
use App\Models\Invoice;

interface PaymentGateway
{
    public function createIntent(Customer $customer, Invoice $invoice, int $amount, string $currency, string $idempotencyKey): PaymentIntentResult;
}

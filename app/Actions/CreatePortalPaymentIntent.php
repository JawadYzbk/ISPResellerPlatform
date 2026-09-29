<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\PaymentIntentResult;
use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use DomainException;

final readonly class CreatePortalPaymentIntent implements Action
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(Customer $customer, Invoice $invoice, int $amount, string $idempotencyKey): PaymentIntentResult
    {
        if ($invoice->tenant_id !== $customer->tenant_id || $invoice->customer_id !== $customer->id || $invoice->status !== InvoiceStatus::Issued) {
            throw new DomainException('The invoice is not payable by this customer.');
        }
        if ($amount < 1 || $amount > $invoice->outstandingAmount()) {
            throw new DomainException('The payment amount exceeds the invoice balance.');
        }

        return $this->gateway->createIntent($customer, $invoice, $amount, $invoice->currency, $idempotencyKey);
    }
}

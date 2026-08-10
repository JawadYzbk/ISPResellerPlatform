<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use DomainException;

final readonly class CreateRenewalInvoice implements Action
{
    public function __construct(private CreateInvoice $createInvoice, private IssueInvoice $issueInvoice) {}

    public function handle(Customer $customer, Service $service, ?User $actor = null, int $periods = 1): Invoice
    {
        if ($periods < 1 || $periods > 12) {
            throw new DomainException('Renewal periods must be between one and twelve.');
        }
        if ($service->tenant_id !== $customer->tenant_id || $service->customer_id !== $customer->id) {
            throw new DomainException('The selected service does not belong to this customer.');
        }
        if ($service->status === ServiceStatus::Terminated) {
            throw new DomainException('Terminated services require an explicit reactivation workflow.');
        }

        $draft = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Draft)
            ->whereHas('lines', fn ($query) => $query->where('service_id', $service->id))
            ->latest('id')
            ->get()
            ->first(fn (Invoice $invoice): bool => $periods === 1 || (int) ($invoice->metadata['renewal_periods'] ?? 1) === $periods);
        if ($draft instanceof Invoice) {
            return $this->issueInvoice->handle($draft, $actor);
        }

        $openInvoice = Invoice::query()
            ->with(['payments.allocations', 'creditNotes'])
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Issued)
            ->whereHas('lines', fn ($query) => $query->where('service_id', $service->id))
            ->latest('id')
            ->get()
            ->first(function (Invoice $invoice) use ($periods): bool {
                if ($periods > 1 && (int) ($invoice->metadata['renewal_periods'] ?? 1) !== $periods) {
                    return false;
                }
                $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount'));
                $credited = $invoice->creditNotes->sum('amount');

                return $invoice->total_amount > $allocated + $credited;
            });
        if ($openInvoice instanceof Invoice) {
            return $openInvoice;
        }

        $invoice = $this->createInvoice->handle($customer, $service->plan, $service, quantity: $periods);
        $invoice->forceFill(['metadata' => ['renewal_periods' => $periods]])->save();

        return $this->issueInvoice->handle($invoice, $actor);
    }
}

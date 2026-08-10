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

    public function handle(Customer $customer, Service $service, ?User $actor = null): Invoice
    {
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
            ->first();
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
            ->first(function (Invoice $invoice): bool {
                $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount'));
                $credited = $invoice->creditNotes->sum('amount');

                return $invoice->total_amount > $allocated + $credited;
            });
        if ($openInvoice instanceof Invoice) {
            return $openInvoice;
        }

        return $this->issueInvoice->handle($this->createInvoice->handle($customer, $service->plan, $service), $actor);
    }
}

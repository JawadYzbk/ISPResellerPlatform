<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Data\BillingCycleQuote;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateRenewalInvoice implements Action
{
    public function __construct(private CreateInvoice $createInvoice, private IssueInvoice $issueInvoice, private PreviewServiceBillingCycle $previewCycle) {}

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

        $cycleQuote = $this->cycleQuote($service, $periods);
        $cycleEnd = $cycleQuote?->endsAt->toIso8601String();
        $draft = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('status', InvoiceStatus::Draft)
            ->whereHas('lines', fn ($query) => $query->where('service_id', $service->id))
            ->latest('id')
            ->get()
            ->first(fn (Invoice $invoice): bool => ($periods === 1 || (int) ($invoice->metadata['renewal_periods'] ?? 1) === $periods)
                && ($cycleEnd === null || ($invoice->metadata['billing_cycle_quote']['ends_at'] ?? null) === $cycleEnd));
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
            ->first(function (Invoice $invoice) use ($periods, $cycleEnd): bool {
                if ($periods > 1 && (int) ($invoice->metadata['renewal_periods'] ?? 1) !== $periods) {
                    return false;
                }
                if ($cycleEnd !== null && ($invoice->metadata['billing_cycle_quote']['ends_at'] ?? null) !== $cycleEnd) {
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

        return DB::transaction(function () use ($customer, $service, $actor, $periods, $cycleQuote): Invoice {
            $invoice = $this->createInvoice->handle(
                $customer,
                $service->plan,
                $service,
                quantity: $periods,
                unitAmount: $cycleQuote?->proratedAmount,
                description: $cycleQuote === null ? null : $service->plan->name.' · prorated billing cycle',
                priceContext: $cycleQuote === null ? [] : ['billing_cycle_quote' => $cycleQuote->toArray()],
            );
            $this->appendRecurringAddons($invoice, $service, $periods, $cycleQuote);
            $invoice->forceFill(['metadata' => array_filter([
                'renewal_periods' => $periods,
                'billing_cycle_quote' => $cycleQuote?->toArray(),
            ], static fn (mixed $value): bool => $value !== null)])->save();

            return $this->issueInvoice->handle($invoice, $actor);
        });
    }

    private function appendRecurringAddons(Invoice $invoice, Service $service, int $periods, ?BillingCycleQuote $cycleQuote): void
    {
        $service->loadMissing('serviceAddons.addon');
        $periodDays = max(1, (int) ($cycleQuote?->cycleDays ?? (($service->plan?->duration_days ?? 30) * $periods)));
        $periodStart = CarbonImmutable::today();
        $periodEnd = $periodStart->addDays($periodDays - 1);
        $total = 0;

        foreach ($service->serviceAddons as $serviceAddon) {
            if ($serviceAddon->status !== 'active' || $serviceAddon->starts_at->greaterThan($periodEnd) || ($serviceAddon->ends_at !== null && $serviceAddon->ends_at->lessThan($periodStart))) {
                continue;
            }
            $addon = $serviceAddon->addon;
            if ($addon === null) {
                continue;
            }
            if (strtoupper((string) $addon->currency) !== strtoupper((string) $invoice->currency)) {
                throw new DomainException("Recurring add-on {$addon->name} uses {$addon->currency}; the renewal invoice uses {$invoice->currency}.");
            }

            $billingPeriodDays = (int) ($addon->billing_period_days ?? 0);
            $occurrences = $billingPeriodDays > 0
                ? (int) ceil($periodDays / $billingPeriodDays)
                : $periods;
            $quantity = max(1, (int) $serviceAddon->quantity) * max(1, $occurrences);
            $lineTotal = (int) $addon->amount_minor * $quantity;
            $invoice->lines()->create([
                'service_id' => $service->id,
                'description' => $addon->name.' · recurring add-on',
                'quantity' => $quantity,
                'unit_amount' => $addon->amount_minor,
                'total_amount' => $lineTotal,
                'currency' => $addon->currency,
                'price_snapshot' => [
                    'kind' => 'recurring_addon',
                    'addon_id' => $addon->id,
                    'addon_public_id' => $addon->public_id,
                    'billing_period_days' => $billingPeriodDays ?: null,
                    'service_period_days' => $periodDays,
                    'quantity' => $quantity,
                ],
            ]);
            $total += $lineTotal;
        }

        if ($total > 0) {
            $invoice->forceFill([
                'subtotal_amount' => $invoice->subtotal_amount + $total,
                'total_amount' => $invoice->total_amount + $total,
            ])->save();
        }
    }

    private function cycleQuote(Service $service, int $periods): ?BillingCycleQuote
    {
        if ($periods !== 1) {
            return null;
        }
        $pending = $service->metadata['pending_billing_cycle'] ?? null;
        $anchorDay = is_array($pending) ? (int) ($pending['anchor_day'] ?? 0) : (int) ($service->billing_anchor_day ?? 0);
        if ($anchorDay < 1 || ($service->expires_at !== null && ! is_array($pending))) {
            return null;
        }

        return $this->previewCycle->handle($service, $anchorDay);
    }
}

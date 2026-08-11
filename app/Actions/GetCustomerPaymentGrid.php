<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use Carbon\CarbonImmutable;

final readonly class GetCustomerPaymentGrid implements Action
{
    /** @return array{year: int, years: list<int>, months: list<array<string, mixed>>} */
    public function handle(Customer $customer, int $year): array
    {
        $year = max(2000, min(2100, $year));
        $start = CarbonImmutable::create($year, 1, 1)->startOfYear();
        $end = $start->endOfYear();

        $invoices = $customer->invoices()
            ->where('status', InvoiceStatus::Issued)
            ->whereNotNull('issued_at')
            ->whereBetween('issued_at', [$start, $end])
            ->with([
                'creditNotes',
                'payments' => fn ($query) => $query
                    ->where('status', PaymentStatus::Posted)
                    ->with('allocations'),
            ])
            ->orderBy('issued_at')
            ->get();

        $months = collect(range(1, 12))->map(function (int $month) use ($invoices): array {
            $monthInvoices = $invoices->filter(fn (Invoice $invoice): bool => $invoice->issued_at?->month === $month);
            $totals = [];
            $paymentCount = 0;

            foreach ($monthInvoices->groupBy('currency') as $currency => $currencyInvoices) {
                $billed = $currencyInvoices->sum('total_amount');
                $credited = $currencyInvoices->sum(fn (Invoice $invoice): int => $invoice->creditNotes->sum('amount'));
                $paid = 0;

                foreach ($currencyInvoices as $invoice) {
                    foreach ($invoice->payments as $payment) {
                        $allocations = $payment->allocations->where('invoice_id', $invoice->id);
                        if ($allocations->isNotEmpty()) {
                            $paymentCount++;
                            $paid += $allocations->sum('amount');
                        }
                    }
                }

                $due = max($billed - $credited, 0);
                $totals[] = [
                    'currency' => $currency,
                    'billed_amount' => $billed,
                    'paid_amount' => $paid,
                    'outstanding_amount' => max($due - $paid, 0),
                ];
            }

            $hasOutstanding = collect($totals)->contains(fn (array $total): bool => $total['outstanding_amount'] > 0);
            $hasPaid = collect($totals)->contains(fn (array $total): bool => $total['paid_amount'] > 0);

            return [
                'month' => $month,
                'status' => $monthInvoices->isEmpty() ? 'no_invoice' : ($hasOutstanding ? ($hasPaid ? 'partial' : 'due') : 'paid'),
                'invoice_count' => $monthInvoices->count(),
                'payment_count' => $paymentCount,
                'totals' => $totals,
            ];
        })->values()->all();

        $firstInvoice = $customer->invoices()->whereNotNull('issued_at')->min('issued_at');
        $firstYear = $firstInvoice === null ? $year : CarbonImmutable::parse($firstInvoice)->year;
        $currentYear = now()->year;

        return [
            'year' => $year,
            'years' => range(min($firstYear, $year), max($currentYear, $year)),
            'months' => $months,
        ];
    }
}

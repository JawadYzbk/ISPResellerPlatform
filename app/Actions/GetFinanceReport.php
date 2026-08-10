<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Carbon\CarbonImmutable;

final readonly class GetFinanceReport implements Action
{
    /** @return array<string, mixed> */
    public function handle(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $invoices = Invoice::query()->where('status', InvoiceStatus::Issued)->whereBetween('issued_at', [$from->startOfDay(), $to->endOfDay()]);
        $payments = Payment::query()->where('status', PaymentStatus::Posted)->whereBetween('received_at', [$from->startOfDay(), $to->endOfDay()]);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'invoice_count' => (int) $invoices->count(),
            'payment_count' => (int) $payments->count(),
            'invoiced_by_currency' => $invoices->clone()->selectRaw('currency, SUM(total_amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all(),
            'collected_by_currency' => $payments->clone()->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency')->map(fn ($value): int => (int) $value)->all(),
            'customer_balances_by_currency' => Customer::query()->selectRaw('balance_currency, SUM(balance_amount) as total')->groupBy('balance_currency')->pluck('total', 'balance_currency')->map(fn ($value): int => (int) $value)->all(),
        ];
    }
}

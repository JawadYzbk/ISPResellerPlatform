<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceStatus;
use App\Models\Invoice;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final readonly class PreviewBulkRenewals implements Action
{
    /** @return array{rows: array<int, array<string, mixed>>, summary: array<string, int>} */
    public function handle(CarbonImmutable $asOf, ?string $search = null, int $limit = 500): array
    {
        $services = Service::query()
            ->with(['customer', 'plan'])
            ->where('status', ServiceStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $asOf->endOfDay())
            ->when(filled($search), fn (Builder $query): Builder => $query->search($search))
            ->orderBy('expires_at')
            ->orderBy('username')
            ->limit(min(max($limit, 1), 500))
            ->get();

        $rows = $services->map(function (Service $service) use ($asOf): array {
            $price = $service->plan?->priceAt($asOf);
            $openInvoice = $this->openInvoice($service);
            $status = $price === null ? 'blocked' : ($openInvoice === null ? 'ready' : 'open');

            return [
                'service_id' => $service->public_id,
                'username' => $service->username,
                'expires_at' => $service->expires_at?->toIso8601String(),
                'customer' => [
                    'public_id' => $service->customer?->public_id,
                    'code' => $service->customer?->code,
                    'name' => $service->customer?->full_name,
                ],
                'plan' => $service->plan?->name,
                'price' => $price === null ? null : [
                    'amount' => $price->amount_minor,
                    'currency' => $price->currency,
                ],
                'status' => $status,
                'can_select' => $status !== 'blocked',
                'reason' => match ($status) {
                    'blocked' => 'No active plan price is available for this date.',
                    'open' => 'An outstanding invoice already exists and will be reused.',
                    default => 'Ready to issue the renewal invoice.',
                },
                'open_invoice' => $openInvoice === null ? null : [
                    'public_id' => $openInvoice->public_id,
                    'number' => $openInvoice->number,
                    'outstanding_amount' => $this->outstanding($openInvoice),
                ],
            ];
        })->values()->all();

        return [
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'ready' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'ready')),
                'open' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'open')),
                'blocked' => count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'blocked')),
            ],
        ];
    }

    private function openInvoice(Service $service): ?Invoice
    {
        return Invoice::query()
            ->with(['payments.allocations', 'creditNotes'])
            ->where('customer_id', $service->customer_id)
            ->where('status', InvoiceStatus::Issued)
            ->whereHas('lines', fn (Builder $query): Builder => $query->where('service_id', $service->id))
            ->latest('id')
            ->get()
            ->first(fn (Invoice $invoice): bool => $this->outstanding($invoice) > 0);
    }

    private function outstanding(Invoice $invoice): int
    {
        $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $credited = $invoice->creditNotes->sum('amount');

        return max(0, $invoice->total_amount - $allocated - $credited);
    }
}

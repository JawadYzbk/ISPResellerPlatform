<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Support\BillingPdfFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenerateInvoicePdf implements Action
{
    public function __construct(private GetInvoiceDetails $getDetails) {}

    public function handle(Invoice $invoice): Response
    {
        $invoice = $this->getDetails->handle($invoice);
        $tenant = Tenant::query()->findOrFail($invoice->tenant_id);
        $allocated = $invoice->payments->sum(fn ($payment): int => $payment->allocations
            ->where('invoice_id', $invoice->id)
            ->sum('amount'));
        $credited = $invoice->creditNotes->sum('amount');

        return Pdf::loadView('pdf.invoice', [
            'tenant' => $tenant,
            'settings' => $tenant->settingsData(),
            'invoice' => $invoice,
            'allocated' => $allocated,
            'credited' => $credited,
            'outstanding' => max(0, $invoice->total_amount - $allocated - $credited),
            'formatter' => BillingPdfFormatter::class,
        ])->setPaper('a4')->download($invoice->number.'.pdf');
    }
}

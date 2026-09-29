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
        $allocated = $invoice->effectiveAllocatedAmount();
        $credited = $invoice->creditNotes->where('status', 'issued')->sum('amount');

        return Pdf::loadView('pdf.invoice', [
            'tenant' => $tenant,
            'settings' => $tenant->settingsData(),
            'invoice' => $invoice,
            'allocated' => $allocated,
            'credited' => $credited,
            'outstanding' => $invoice->outstandingAmount(),
            'formatter' => BillingPdfFormatter::class,
        ])->setPaper('a4')->download($invoice->number.'.pdf');
    }
}

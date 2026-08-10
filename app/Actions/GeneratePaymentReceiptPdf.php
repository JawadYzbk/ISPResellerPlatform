<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\BillingPdfFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final readonly class GeneratePaymentReceiptPdf implements Action
{
    public function __construct(private GetPaymentDetails $getDetails) {}

    public function handle(Payment $payment): Response
    {
        $payment = $this->getDetails->handle($payment);
        $tenant = Tenant::query()->findOrFail($payment->tenant_id);

        return Pdf::loadView('pdf.receipt', [
            'tenant' => $tenant,
            'settings' => $tenant->settingsData(),
            'payment' => $payment,
            'formatter' => BillingPdfFormatter::class,
        ])->setPaper('a4')->download($payment->number.'.pdf');
    }
}

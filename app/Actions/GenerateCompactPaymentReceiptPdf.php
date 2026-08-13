<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Payment;
use App\Models\Tenant;
use App\Support\BillingPdfFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

final readonly class GenerateCompactPaymentReceiptPdf implements Action
{
    public function __construct(private GetPaymentDetails $getDetails) {}

    public function handle(Payment $payment, int $widthMm = 80): Response
    {
        $widthMm = $widthMm === 58 ? 58 : 80;
        $payment = $this->getDetails->handle($payment);
        $tenant = Tenant::query()->findOrFail($payment->tenant_id);
        $pointsPerMm = 72 / 25.4;
        $logoDataUri = null;
        if (is_string($tenant->logo_path) && $tenant->logo_path !== '') {
            $disk = Storage::disk((string) config('filesystems.default', 'local'));
            if ($disk->exists($tenant->logo_path)) {
                $logoDataUri = 'data:'.($disk->mimeType($tenant->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($tenant->logo_path));
            }
        }

        return Pdf::loadView('pdf.receipt-compact', [
            'tenant' => $tenant,
            'settings' => $tenant->settingsData(),
            'payment' => $payment,
            'formatter' => BillingPdfFormatter::class,
            'widthMm' => $widthMm,
            'logoDataUri' => $logoDataUri,
        ])->setPaper([0, 0, $widthMm * $pointsPerMm, 220 * $pointsPerMm])
            ->download($payment->number.'-'.$widthMm.'mm.pdf');
    }
}

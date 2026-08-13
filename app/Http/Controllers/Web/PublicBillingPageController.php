<?php

namespace App\Http\Controllers\Web;

use App\Actions\GenerateCompactPaymentReceiptPdf;
use App\Actions\GenerateInvoicePdf;
use App\Actions\GeneratePaymentReceiptPdf;
use App\Actions\GetPublicBillingPageData;
use App\Actions\ResolvePublicBillingLink;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class PublicBillingPageController extends Controller
{
    public function show(string $token, GetPublicBillingPageData $data): Response
    {
        return Inertia::render('Public/Billing', [
            'token' => $token,
            ...$data->handle($token),
        ]);
    }

    public function pdf(Request $request, string $token, ResolvePublicBillingLink $resolve, GenerateInvoicePdf $invoicePdf, GeneratePaymentReceiptPdf $receiptPdf, GenerateCompactPaymentReceiptPdf $compactPdf): HttpResponse
    {
        $link = $resolve->handle($token, false);
        if (in_array($link->type, ['invoice', 'payment'], true) && $link->invoice !== null) {
            return $invoicePdf->handle($link->invoice);
        }
        abort_unless($link->type === 'receipt' && $link->payment !== null, 404);
        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:a4,compact'],
            'width' => ['nullable', 'integer', 'in:58,80'],
        ]);

        return ($validated['format'] ?? 'a4') === 'compact'
            ? $compactPdf->handle($link->payment, (int) ($validated['width'] ?? 80))
            : $receiptPdf->handle($link->payment);
    }
}

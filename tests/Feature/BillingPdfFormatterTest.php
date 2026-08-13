<?php

use App\Support\BillingPdfFormatter;

it('localizes billing document labels and payment methods', function (): void {
    expect(BillingPdfFormatter::label('Invoice', 'ar'))->toBe('فاتورة')
        ->and(BillingPdfFormatter::label('Amount received', 'fr'))->toBe('Montant reçu')
        ->and(BillingPdfFormatter::label('mobile_wallet', 'ar'))->toBe('محفظة إلكترونية')
        ->and(BillingPdfFormatter::label('mobile_wallet', 'fr'))->toBe('Portefeuille mobile');
});

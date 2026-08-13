<?php

namespace App\Support;

use Brick\Money\Money as BrickMoney;
use Carbon\CarbonInterface;

final class BillingPdfFormatter
{
    /** @var array<string, array<string, string>> */
    private const LABELS = [
        'en' => [
            'Invoice' => 'Invoice',
            'Receipt' => 'Receipt',
            'Receipt number' => 'Receipt',
            'Payment receipt' => 'Payment receipt',
            'Bill to' => 'Bill to',
            'Dates' => 'Dates',
            'Issued' => 'Issued',
            'Due' => 'Due',
            'Description' => 'Description',
            'Qty' => 'Qty',
            'Unit' => 'Unit',
            'Amount' => 'Amount',
            'Subtotal' => 'Subtotal',
            'Tax' => 'Tax',
            'Total' => 'Total',
            'Paid' => 'Paid',
            'Credits' => 'Credits',
            'Outstanding' => 'Outstanding',
            'Received from' => 'Received from',
            'Received at' => 'Received at',
            'Method' => 'Method',
            'Collector' => 'Collector',
            'Amount received' => 'Amount received',
            'Unallocated payment' => 'Unallocated payment',
            'Ledger equivalent' => 'Ledger equivalent',
            'Base equivalent' => 'Base equivalent',
            'Reference' => 'Reference',
            'FX rate' => 'FX rate',
            'Current rate' => 'Current rate',
            'Invoice allocations' => 'Invoice allocations',
            'Generated' => 'Generated',
            'Date' => 'Date',
            'Customer' => 'Customer',
            'Account' => 'Account',
            'Applied to' => 'Applied to',
            'Thank you.' => 'Thank you.',
            'Keep this receipt for your records.' => 'Keep this receipt for your records.',
            'REVERSED — NOT VALID FOR PAYMENT' => 'REVERSED — NOT VALID FOR PAYMENT',
            'posted' => 'Posted',
            'reversed' => 'Reversed',
            'draft' => 'Draft',
            'issued' => 'Issued',
            'void' => 'Void',
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank transfer',
            'whish' => 'Whish',
            'stripe' => 'Stripe',
        ],
        'ar' => [
            'Invoice' => 'فاتورة',
            'Receipt' => 'إيصال',
            'Receipt number' => 'الإيصال',
            'Payment receipt' => 'إيصال الدفع',
            'Bill to' => 'الفاتورة إلى',
            'Dates' => 'التواريخ',
            'Issued' => 'صدر في',
            'Due' => 'يستحق في',
            'Description' => 'الوصف',
            'Qty' => 'الكمية',
            'Unit' => 'الوحدة',
            'Amount' => 'المبلغ',
            'Subtotal' => 'المجموع الفرعي',
            'Tax' => 'الضريبة',
            'Total' => 'الإجمالي',
            'Paid' => 'المدفوع',
            'Credits' => 'الأرصدة الدائنة',
            'Outstanding' => 'المستحق',
            'Received from' => 'مستلم من',
            'Received at' => 'تاريخ الاستلام',
            'Method' => 'طريقة الدفع',
            'Collector' => 'المحصّل',
            'Amount received' => 'المبلغ المستلم',
            'Unallocated payment' => 'دفعة غير مخصصة',
            'Ledger equivalent' => 'المقابل في دفتر الأستاذ',
            'Base equivalent' => 'المقابل بالعملة الأساسية',
            'Reference' => 'المرجع',
            'FX rate' => 'سعر الصرف',
            'Current rate' => 'السعر الحالي',
            'Invoice allocations' => 'تخصيصات الفواتير',
            'Generated' => 'تم الإنشاء',
            'Date' => 'التاريخ',
            'Customer' => 'العميل',
            'Account' => 'الحساب',
            'Applied to' => 'مخصص لـ',
            'Thank you.' => 'شكرًا لك.',
            'Keep this receipt for your records.' => 'احتفظ بهذا الإيصال لسجلاتك.',
            'REVERSED — NOT VALID FOR PAYMENT' => 'ملغى — غير صالح للدفع',
            'posted' => 'مسجّل',
            'reversed' => 'معكوس',
            'draft' => 'مسودة',
            'issued' => 'صادرة',
            'void' => 'ملغاة',
            'cash' => 'نقدًا',
            'card' => 'بطاقة',
            'bank_transfer' => 'تحويل مصرفي',
            'whish' => 'Whish',
            'stripe' => 'Stripe',
        ],
        'fr' => [
            'Invoice' => 'Facture',
            'Receipt' => 'Reçu',
            'Receipt number' => 'Reçu',
            'Payment receipt' => 'Reçu de paiement',
            'Bill to' => 'Facturer à',
            'Dates' => 'Dates',
            'Issued' => 'Émise',
            'Due' => 'Échéance',
            'Description' => 'Description',
            'Qty' => 'Qté',
            'Unit' => 'Unité',
            'Amount' => 'Montant',
            'Subtotal' => 'Sous-total',
            'Tax' => 'Taxe',
            'Total' => 'Total',
            'Paid' => 'Payé',
            'Credits' => 'Avoirs',
            'Outstanding' => 'Solde dû',
            'Received from' => 'Reçu de',
            'Received at' => 'Reçu le',
            'Method' => 'Mode de paiement',
            'Collector' => 'Collecteur',
            'Amount received' => 'Montant reçu',
            'Unallocated payment' => 'Paiement non affecté',
            'Ledger equivalent' => 'Équivalent dans le grand livre',
            'Base equivalent' => 'Équivalent dans la devise de base',
            'Reference' => 'Référence',
            'FX rate' => 'Taux de change',
            'Current rate' => 'Taux actuel',
            'Invoice allocations' => 'Affectations de factures',
            'Generated' => 'Généré',
            'Date' => 'Date',
            'Customer' => 'Client',
            'Account' => 'Compte',
            'Applied to' => 'Affecté à',
            'Thank you.' => 'Merci.',
            'Keep this receipt for your records.' => 'Conservez ce reçu pour vos archives.',
            'REVERSED — NOT VALID FOR PAYMENT' => 'ANNULÉ — NON VALABLE POUR LE PAIEMENT',
            'posted' => 'Comptabilisé',
            'reversed' => 'Annulé',
            'draft' => 'Brouillon',
            'issued' => 'Émise',
            'void' => 'Annulée',
            'cash' => 'Espèces',
            'card' => 'Carte',
            'bank_transfer' => 'Virement bancaire',
            'whish' => 'Whish',
            'stripe' => 'Stripe',
        ],
    ];

    public static function label(string $key, string $locale = 'en'): string
    {
        $locale = in_array($locale, ['en', 'ar', 'fr'], true) ? $locale : 'en';

        return self::LABELS[$locale][$key] ?? self::LABELS['en'][$key] ?? $key;
    }

    public static function money(int $amount, string $currency, string $locale = 'en'): string
    {
        $formatLocale = match ($locale) {
            'ar' => 'ar_LB',
            'fr' => 'fr_FR',
            default => 'en_US',
        };

        try {
            return BrickMoney::ofMinor($amount, $currency)->formatToLocale($formatLocale);
        } catch (\Throwable) {
            return BrickMoney::ofMinor($amount, $currency)->formatToLocale('en_US');
        }
    }

    public static function date(?CarbonInterface $date, string $timezone): string
    {
        return $date?->setTimezone($timezone)->format('Y-m-d H:i') ?? '—';
    }
}

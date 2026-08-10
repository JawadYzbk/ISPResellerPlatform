<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Domain\Ledger\JournalLineInput;
use App\Domain\Ledger\PostJournalEntry;
use App\Domain\Money\FxConverter;
use App\Enums\CashShiftStatus;
use App\Enums\FxRoundingMode;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DocumentNumberGenerator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordPayment implements Action
{
    public function __construct(private DocumentNumberGenerator $numbers, private PostJournalEntry $journal, private RenewService $renewService, private QueueCustomerNotification $notify, private FxConverter $fx) {}

    /** @param array<string, mixed> $metadata */
    public function handle(Customer $customer, int $amount, string $currency, string $method, string $idempotencyKey, ?Invoice $invoice = null, ?User $actor = null, ?CashShift $cashShift = null, ?int $fxRateNumerator = null, ?int $fxRateDenominator = null, ?string $fxOverrideReason = null, ?string $reference = null, ?string $roundingMode = null, array $metadata = []): Payment
    {
        if ($amount < 1) {
            throw new DomainException('Payment amount must be positive.');
        }
        if (($fxRateNumerator === null) xor ($fxRateDenominator === null)) {
            throw new DomainException('Both FX override ratio values are required.');
        }
        if ($fxRateNumerator !== null && ($fxRateNumerator < 1 || $fxRateDenominator === null || $fxRateDenominator < 1)) {
            throw new DomainException('FX override ratio values must be positive.');
        }
        if (($fxRateNumerator !== null || $fxRateDenominator !== null) && blank($fxOverrideReason)) {
            throw new DomainException('An explanation is required when overriding the FX rate.');
        }
        $rounding = FxRoundingMode::tryFrom($roundingMode ?: (string) config('services.fx.rounding_mode', FxRoundingMode::HalfUp->value));
        if ($rounding === null) {
            throw new DomainException('Unsupported FX rounding mode.');
        }
        if ($invoice !== null && ($invoice->tenant_id !== $customer->tenant_id || $invoice->customer_id !== $customer->id || $invoice->status !== InvoiceStatus::Issued)) {
            throw new DomainException('The invoice is not payable by this customer.');
        }
        if ($cashShift !== null && $cashShift->status !== CashShiftStatus::Open) {
            throw new DomainException('Payments cannot be recorded to a closed cash shift.');
        }

        $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing instanceof Payment) {
            return $existing->load('allocations');
        }

        $receivedAt = CarbonImmutable::now();
        $baseCurrency = (string) Tenant::query()->whereKey($customer->tenant_id)->value('base_currency');
        $baseSnapshot = $this->fx->snapshot($currency, $baseCurrency, $receivedAt, $fxRateNumerator, $fxRateDenominator, $rounding->value);
        $ledgerSnapshot = $customer->balance_currency === $baseCurrency
            ? $baseSnapshot
            : $this->fx->snapshot($currency, $customer->balance_currency, $receivedAt, roundingMode: $rounding->value);
        if ($invoice !== null && $invoice->currency !== $baseCurrency && ($fxRateNumerator !== null || $fxRateDenominator !== null)) {
            throw new DomainException('FX overrides must be stated against the tenant base currency.');
        }
        $invoiceSnapshot = null;
        if ($invoice !== null) {
            $invoiceSnapshot = match (true) {
                $invoice->currency === $baseCurrency => $baseSnapshot,
                $invoice->currency === $customer->balance_currency => $ledgerSnapshot,
                default => $this->fx->snapshot($currency, $invoice->currency, $receivedAt, roundingMode: $rounding->value),
            };
        }

        try {
            $payment = DB::transaction(function () use ($customer, $amount, $currency, $method, $idempotencyKey, $invoice, $actor, $cashShift, $fxOverrideReason, $reference, $receivedAt, $baseCurrency, $baseSnapshot, $ledgerSnapshot, $invoiceSnapshot, $metadata): Payment {
                $cash = LedgerAccount::query()->where('code', '1000')->firstOrFail();
                $receivable = LedgerAccount::query()->where('code', '1100')->firstOrFail();
                $lockedInvoice = null;
                $invoiceAmount = null;
                $outstanding = null;
                if ($invoice !== null && $invoiceSnapshot !== null) {
                    $lockedInvoice = Invoice::query()->with(['payments.allocations', 'creditNotes', 'lines.service'])->lockForUpdate()->findOrFail($invoice->id);
                    $allocated = $lockedInvoice->payments->sum(fn ($payment): int => $payment->allocations
                        ->where('invoice_id', $lockedInvoice->id)
                        ->sum('amount'));
                    $credited = $lockedInvoice->creditNotes->sum('amount');
                    $outstanding = max(0, $lockedInvoice->total_amount - $allocated - $credited);
                    if ($outstanding < 1) {
                        throw new DomainException('The selected invoice has no outstanding balance.');
                    }
                    $invoiceAmount = $invoiceSnapshot->convert($amount);
                }
                $ledgerAmount = $ledgerSnapshot->convert($amount);
                $baseAmount = $baseSnapshot->convert($amount);
                $payment = Payment::create([
                    'number' => $this->numbers->next('receipt', 'RCT'),
                    'customer_id' => $customer->id,
                    'invoice_id' => $lockedInvoice?->id,
                    'cash_shift_id' => $cashShift?->id,
                    'status' => PaymentStatus::Posted,
                    'amount' => $amount,
                    'ledger_amount' => $ledgerAmount,
                    'ledger_currency' => $ledgerSnapshot->targetCurrency,
                    'base_amount' => $baseAmount,
                    'currency' => $currency,
                    'fx_rate_numerator' => $baseSnapshot->numerator,
                    'fx_rate_denominator' => $baseSnapshot->denominator,
                    'fx_rate_overridden' => $baseSnapshot->overridden,
                    'fx_override_reason' => $baseSnapshot->overridden ? $fxOverrideReason : null,
                    'reference' => $reference,
                    'method' => $method,
                    'idempotency_key' => $idempotencyKey,
                    'received_at' => $receivedAt,
                    'actor_id' => $actor?->id,
                    'metadata' => [
                        'base_currency' => $baseCurrency,
                        'base_fx_source' => $baseSnapshot->rateSource ?? $baseSnapshot->source,
                        'base_fx_snapshot' => $baseSnapshot->toArray(),
                        'ledger_fx' => $ledgerSnapshot->toArray(),
                        'invoice_amount' => $invoiceAmount,
                        'invoice_currency' => $lockedInvoice?->currency,
                        ...$metadata,
                    ],
                ]);
                if ($lockedInvoice !== null && $invoiceAmount !== null && $outstanding !== null) {
                    $allocationAmount = min($invoiceAmount, $outstanding);
                    $payment->allocations()->create(['invoice_id' => $lockedInvoice->id, 'amount' => $allocationAmount, 'currency' => $lockedInvoice->currency]);
                }
                $this->journal->post(
                    'Payment '.$payment->number,
                    [
                        new JournalLineInput($cash->id, $ledgerSnapshot->targetCurrency, debitAmount: $ledgerAmount),
                        new JournalLineInput($receivable->id, $ledgerSnapshot->targetCurrency, creditAmount: $ledgerAmount, customerId: $customer->id),
                    ],
                    actor: $actor,
                    sourceType: Payment::class,
                    sourceId: (string) $payment->id,
                );

                if ($lockedInvoice !== null && $invoiceAmount !== null && $outstanding !== null && $invoiceAmount >= $outstanding) {
                    $renewalPeriods = max(1, (int) (($lockedInvoice->metadata ?? [])['renewal_periods'] ?? 1));
                    foreach ($lockedInvoice->lines as $line) {
                        if ($line->service !== null) {
                            $this->renewService->handle($line->service, $actor, $renewalPeriods);
                        }
                    }
                }

                return $payment->load('allocations');
            });

            $this->notify->handle($customer, 'payment.receipt', 'payment-receipt:'.$payment->id, [
                'customer_name' => $customer->full_name,
                'receipt_number' => $payment->number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            return $payment;
        } catch (UniqueConstraintViolationException) {
            return Payment::query()->where('idempotency_key', $idempotencyKey)->firstOrFail()->load('allocations');
        }
    }
}

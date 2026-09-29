<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property InvoiceStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $issued_at
 * @property Carbon|null $voided_at
 * @property int $total_amount
 */
class Invoice extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'customer_id', 'status', 'currency', 'subtotal_amount', 'tax_amount', 'total_amount', 'due_at', 'issued_at', 'voided_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => InvoiceStatus::class, 'subtotal_amount' => 'integer', 'tax_amount' => 'integer', 'total_amount' => 'integer', 'due_at' => 'datetime', 'issued_at' => 'datetime', 'voided_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function effectiveAllocatedAmount(?CarbonInterface $asOf = null): int
    {
        if ($this->relationLoaded('paymentAllocations') && $this->paymentAllocations->every(fn (PaymentAllocation $allocation): bool => $allocation->relationLoaded('payment'))) {
            return (int) $this->paymentAllocations->sum(fn (PaymentAllocation $allocation): int => $allocation->payment->isEffectiveAt($asOf)
                && ($asOf === null || $allocation->created_at?->lessThanOrEqualTo($asOf) === true)
                ? $allocation->amount
                : 0);
        }

        $allocations = $this->paymentAllocations()
            ->whereIn('payment_id', Payment::query()->effectiveAt($asOf)->select('id'));

        if ($asOf !== null) {
            $allocations->where('created_at', '<=', $asOf);
        }

        return (int) $allocations->sum('amount');
    }

    public function outstandingAmount(?CarbonInterface $asOf = null): int
    {
        return max(0, $this->total_amount - $this->effectiveAllocatedAmount($asOf) - $this->creditedAmount($asOf));
    }

    /**
     * ponytail: legacy replay is quadratic in invoice allocations; record settlement events if large histories become common.
     */
    public function wasPreviouslySettledByPayment(): bool
    {
        $allocations = $this->relationLoaded('paymentAllocations')
            ? $this->paymentAllocations
            : $this->paymentAllocations()->with('payment')->get();
        $allocations->loadMissing('payment');
        /** @var list<array{at: CarbonInterface, payment: Payment}> $settlementEvents */
        $settlementEvents = [];
        $ambiguousHistory = false;

        foreach ($allocations as $allocation) {
            $payment = $allocation->payment;
            if ($payment->invoice_id !== $this->id || ! in_array($payment->status, [PaymentStatus::Posted, PaymentStatus::Reversed], true)) {
                continue;
            }

            $receivedAt = $payment->received_at ?? $payment->created_at;
            $allocatedAt = $allocation->created_at;
            if ($receivedAt === null || $allocatedAt === null || ($payment->status === PaymentStatus::Reversed && $payment->reversed_at === null)) {
                $ambiguousHistory = true;

                continue;
            }
            $settlementEvents[] = ['at' => $receivedAt->greaterThan($allocatedAt) ? $receivedAt : $allocatedAt, 'payment' => $payment];
        }

        usort($settlementEvents, static fn (array $left, array $right): int => $left['at']->getTimestamp() <=> $right['at']->getTimestamp());

        foreach ($settlementEvents as $settlementEvent) {
            $payment = $settlementEvent['payment'];
            if (! $payment->isEffectiveAt($settlementEvent['at'])) {
                if ($payment->reversed_at?->getTimestamp() === $settlementEvent['at']->getTimestamp()) {
                    $ambiguousHistory = true;
                }

                continue;
            }
            if ($this->outstandingAmount($settlementEvent['at']) === 0) {
                return true;
            }
        }

        if ($ambiguousHistory) {
            throw new DomainException('This invoice has legacy payment history without reliable timestamps. Reconcile the payment history before recording another payment.');
        }

        return false;
    }

    private function creditedAmount(?CarbonInterface $asOf): int
    {
        if ($this->relationLoaded('creditNotes')) {
            return (int) $this->creditNotes
                ->filter(fn (CreditNote $creditNote): bool => $creditNote->status === 'issued'
                    && ($asOf === null || $creditNote->issued_at?->lessThanOrEqualTo($asOf) === true))
                ->sum('amount');
        }

        $creditNotes = $this->creditNotes()->where('status', 'issued');
        if ($asOf !== null) {
            $creditNotes->where('issued_at', '<=', $asOf);
        }

        return (int) $creditNotes->sum('amount');
    }
}

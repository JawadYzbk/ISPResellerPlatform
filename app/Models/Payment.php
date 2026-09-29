<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property PaymentStatus $status
 * @property Carbon|null $received_at
 * @property Carbon|null $reversed_at
 * @property CashShift|null $cashShift
 * @property Customer $customer
 * @property Invoice|null $invoice
 * @property User|null $actor
 */
class Payment extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'customer_id', 'invoice_id', 'cash_shift_id', 'status', 'amount', 'ledger_amount', 'ledger_currency', 'base_amount', 'currency', 'fx_rate_numerator', 'fx_rate_denominator', 'fx_rate_overridden', 'fx_override_reason', 'reference', 'method', 'idempotency_key', 'received_at', 'reversed_at', 'reversal_of_id', 'metadata', 'actor_id'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'integer', 'ledger_amount' => 'integer', 'base_amount' => 'integer', 'fx_rate_numerator' => 'integer', 'fx_rate_denominator' => 'integer', 'fx_rate_overridden' => 'boolean', 'received_at' => 'datetime', 'reversed_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->public_id ??= (string) Str::ulid();
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @param Builder<Payment> $query */
    public function scopeEffectiveAt(Builder $query, ?CarbonInterface $asOf = null): Builder
    {
        if ($asOf === null) {
            return $query->where('status', PaymentStatus::Posted)->whereNull('reversed_at');
        }

        return $query
            ->where(function (Builder $query) use ($asOf): void {
                $query->where('received_at', '<=', $asOf)
                    ->orWhere(fn (Builder $query): Builder => $query->whereNull('received_at')->where('created_at', '<=', $asOf));
            })
            ->where(function (Builder $query) use ($asOf): void {
                $query->where(function (Builder $query) use ($asOf): void {
                    $query->where('status', PaymentStatus::Posted)
                        ->where(fn (Builder $query): Builder => $query->whereNull('reversed_at')->orWhere('reversed_at', '>', $asOf));
                })->orWhere(function (Builder $query) use ($asOf): void {
                    $query->where('status', PaymentStatus::Reversed)->where('reversed_at', '>', $asOf);
                });
            });
    }

    public function isEffectiveAt(?CarbonInterface $asOf = null): bool
    {
        if ($asOf === null) {
            return $this->status === PaymentStatus::Posted && $this->reversed_at === null;
        }

        $receivedAt = $this->received_at ?? $this->created_at;
        if ($receivedAt === null || $receivedAt->greaterThan($asOf)) {
            return false;
        }

        return $this->reversed_at === null
            ? $this->status === PaymentStatus::Posted
            : $this->reversed_at->greaterThan($asOf) && in_array($this->status, [PaymentStatus::Posted, PaymentStatus::Reversed], true);
    }
}

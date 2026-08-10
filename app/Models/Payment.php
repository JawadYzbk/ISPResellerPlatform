<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
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
}

<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property PaymentStatus $status */
class Payment extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'customer_id', 'invoice_id', 'cash_shift_id', 'status', 'amount', 'currency', 'method', 'idempotency_key', 'received_at', 'reversed_at', 'reversal_of_id', 'metadata', 'actor_id'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'integer', 'received_at' => 'datetime', 'reversed_at' => 'datetime', 'metadata' => 'array'];
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

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}

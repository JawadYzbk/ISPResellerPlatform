<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $sold_at
 * @property Customer|null $customer
 * @property Invoice|null $invoice
 * @property Payment|null $payment
 */
class FieldInventorySale extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'customer_id', 'warehouse_id', 'collector_id', 'invoice_id', 'payment_id', 'status', 'currency', 'total_amount', 'payment_method', 'idempotency_key', 'note', 'sold_at'];

    protected function casts(): array
    {
        return ['total_amount' => 'integer', 'sold_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            $sale->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<FieldInventorySaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FieldInventorySaleLine::class);
    }
}

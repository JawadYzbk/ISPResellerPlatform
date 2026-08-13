<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

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

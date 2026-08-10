<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property InvoiceStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $issued_at
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
}

<?php

namespace App\Models;

use App\Enums\PaymentAttemptStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property PaymentAttemptStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $last_checked_at
 */
class PaymentAttempt extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'public_id',
        'gateway',
        'external_id',
        'customer_id',
        'invoice_id',
        'actor_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'idempotency_key',
        'invoice_reference',
        'collect_url',
        'provider_transaction_id',
        'failure_reason',
        'paid_at',
        'last_checked_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentAttemptStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            $attempt->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

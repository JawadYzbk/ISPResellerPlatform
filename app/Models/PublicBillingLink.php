<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $last_accessed_at
 */
class PublicBillingLink extends Model
{
    use Auditable, BelongsToTenant;

    public const TYPES = ['invoice', 'statement', 'payment', 'receipt'];

    protected $fillable = [
        'tenant_id', 'public_id', 'token_hash', 'type', 'customer_id', 'invoice_id', 'payment_id',
        'created_by_id', 'expires_at', 'revoked_at', 'last_accessed_at', 'access_count',
    ];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_accessed_at' => 'datetime',
            'access_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link): void {
            $link->public_id ??= (string) Str::ulid();
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

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

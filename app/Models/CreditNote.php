<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $amount
 * @property Carbon $issued_at
 */
class CreditNote extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'invoice_id', 'customer_id', 'number', 'amount', 'currency', 'status', 'reason', 'issued_at', 'created_by_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'issued_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note): void {
            $note->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

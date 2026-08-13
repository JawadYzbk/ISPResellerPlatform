<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $occurred_at
 * @property Carbon|null $reviewed_at
 */
class CollectorCustodyEntry extends Model
{
    use Auditable, BelongsToTenant;

    public const TYPES = ['advance', 'expense', 'handover', 'adjustment'];

    public const DIRECTIONS = ['credit', 'debit'];

    public const STATUSES = ['pending', 'posted', 'rejected'];

    protected $fillable = [
        'tenant_id', 'public_id', 'collector_id', 'cash_shift_id', 'requested_by_id',
        'reviewed_by_id', 'type', 'direction', 'status', 'amount', 'currency',
        'description', 'reference', 'occurred_at', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'occurred_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return BelongsTo<CashShift, $this> */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }
}

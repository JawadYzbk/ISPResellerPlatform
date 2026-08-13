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
 * @property Carbon $incurred_at
 * @property Carbon|null $reviewed_at
 */
class OperationalExpense extends Model
{
    use Auditable, BelongsToTenant;

    public const STATUSES = ['pending', 'posted', 'rejected'];

    public const PAYMENT_SOURCES = ['cash', 'bank', 'collector'];

    protected $fillable = [
        'tenant_id', 'public_id', 'expense_category_id', 'expense_vendor_id', 'requested_by_id',
        'reviewed_by_id', 'collector_id', 'cash_shift_id', 'journal_entry_id', 'collector_custody_entry_id',
        'status', 'payment_source', 'amount', 'currency', 'description', 'reference', 'incurred_at',
        'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'incurred_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            $expense->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<ExpenseVendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(ExpenseVendor::class, 'expense_vendor_id');
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

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** @return BelongsTo<CashShift, $this> */
    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<CollectorCustodyEntry, $this> */
    public function collectorCustodyEntry(): BelongsTo
    {
        return $this->belongsTo(CollectorCustodyEntry::class);
    }

    /** @return HasMany<MediaUpload, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaUpload::class);
    }
}

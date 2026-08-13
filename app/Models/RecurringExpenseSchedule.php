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
 * @property Carbon $starts_on
 * @property Carbon $next_run_on
 * @property Carbon|null $ends_on
 */
class RecurringExpenseSchedule extends Model
{
    use Auditable, BelongsToTenant;

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'tenant_id', 'public_id', 'expense_category_id', 'expense_vendor_id', 'created_by_id',
        'frequency', 'interval', 'payment_source', 'amount', 'currency', 'description', 'reference',
        'starts_on', 'next_run_on', 'ends_on', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'interval' => 'integer', 'starts_on' => 'date', 'next_run_on' => 'date',
            'ends_on' => 'date', 'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            $schedule->public_id ??= (string) Str::ulid();
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<OperationalExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(OperationalExpense::class);
    }
}

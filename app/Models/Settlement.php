<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 */
class Settlement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'partner_id', 'period_start', 'period_end', 'currency', 'opening_amount', 'activity_amount', 'closing_amount', 'due_amount', 'status', 'approved_by', 'approved_at', 'journal_entry_id', 'paid_at'];

    protected function casts(): array
    {
        return ['opening_amount' => 'integer', 'activity_amount' => 'integer', 'closing_amount' => 'integer', 'due_amount' => 'integer', 'period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $settlement): void {
            $settlement->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

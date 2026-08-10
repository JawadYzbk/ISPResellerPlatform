<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property User $user */
class CashShift extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'user_id', 'status', 'opened_at', 'closed_at', 'system_totals', 'declared_totals', 'variance', 'variance_note'];

    protected function casts(): array
    {
        return ['status' => CashShiftStatus::class, 'opened_at' => 'datetime', 'closed_at' => 'datetime', 'system_totals' => 'array', 'declared_totals' => 'array', 'variance' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $shift): void {
            $shift->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

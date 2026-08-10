<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class Promotion extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'name', 'code', 'type', 'value', 'applies_to', 'starts_at', 'ends_at', 'max_redemptions', 'redemptions_count', 'is_active'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'applies_to' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'max_redemptions' => 'integer', 'redemptions_count' => 'integer', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $promotion): void {
            $promotion->public_id ??= (string) Str::ulid();
            $promotion->code = strtoupper(trim($promotion->code));
        });
        static::updating(function (self $promotion): void {
            if ($promotion->isDirty('code')) {
                $promotion->code = strtoupper(trim($promotion->code));
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

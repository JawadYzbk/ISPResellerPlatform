<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class CommissionRule extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'partner_id', 'type', 'value', 'plan_id', 'zone_id', 'effective_from', 'effective_to', 'version', 'status'];

    protected function casts(): array
    {
        return ['value' => 'integer', 'version' => 'integer', 'effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return HasMany<PriceBookItem, $this> */
    public function priceBookItems(): HasMany
    {
        return $this->hasMany(PriceBookItem::class);
    }

    public function isEffectiveAt(Carbon $at): bool
    {
        return $this->status === 'active'
            && $this->effective_from->lessThanOrEqualTo($at)
            && ($this->effective_to === null || $this->effective_to->greaterThan($at));
    }
}

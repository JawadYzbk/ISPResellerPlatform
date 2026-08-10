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
class PriceBook extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'partner_id', 'name', 'status', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<PriceBookItem, $this> */
    public function items(): HasMany
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

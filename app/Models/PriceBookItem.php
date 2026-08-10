<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class PriceBookItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'price_book_id', 'plan_id', 'commission_rule_id', 'currency', 'buy_amount_minor', 'sell_amount_minor', 'min_amount_minor', 'max_amount_minor', 'effective_from', 'effective_to'];

    protected function casts(): array
    {
        return ['buy_amount_minor' => 'integer', 'sell_amount_minor' => 'integer', 'min_amount_minor' => 'integer', 'max_amount_minor' => 'integer', 'effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(PriceBook::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function commissionRule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class);
    }

    public function isEffectiveAt(Carbon $at): bool
    {
        return $this->effective_from->lessThanOrEqualTo($at)
            && ($this->effective_to === null || $this->effective_to->greaterThan($at));
    }
}

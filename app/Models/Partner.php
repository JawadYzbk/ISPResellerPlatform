<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $path
 * @property int $depth
 * @property int $credit_limit
 */
class Partner extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'parent_id', 'name', 'code', 'path', 'depth', 'status', 'currency', 'credit_limit', 'low_balance_threshold'];

    protected function casts(): array
    {
        return ['depth' => 'integer', 'credit_limit' => 'integer', 'low_balance_threshold' => 'integer'];
    }

    protected static function booted(): void
    {
        static::created(function (self $partner): void {
            $path = $partner->parent_id === null ? '/' : $partner->parent->path;
            $depth = $partner->parent_id === null ? 0 : $partner->parent->depth + 1;
            $partner->forceFill(['path' => $path.$partner->id.'/', 'depth' => $depth])->saveQuietly();
            PartnerWallet::firstOrCreate(['partner_id' => $partner->id, 'currency' => $partner->currency], ['balance_amount' => 0]);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasOne<PartnerWallet, $this> */
    public function wallet(): HasOne
    {
        return $this->hasOne(PartnerWallet::class);
    }

    /** @param Builder<Partner> $query */
    public function scopeDescendants(Builder $query, self $partner): Builder
    {
        return $query->where('path', 'like', $partner->path.'%');
    }
}

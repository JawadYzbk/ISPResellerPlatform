<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class InventoryStockCount extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'warehouse_id', 'counted_by_id', 'reviewed_by_id', 'status', 'note', 'review_note', 'counted_at', 'reviewed_at'];

    protected function casts(): array
    {
        return ['counted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $count): void {
            $count->public_id ??= (string) Str::ulid();
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /** @return HasMany<InventoryStockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryStockCountLine::class);
    }
}

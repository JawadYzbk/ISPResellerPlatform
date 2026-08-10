<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property string $sku @property string $name */
class InventoryItem extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'sku', 'name', 'category', 'is_serialized', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['is_serialized' => 'boolean', 'reorder_level' => 'integer', 'is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }
}

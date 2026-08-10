<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'code', 'type', 'assigned_user_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<InventoryUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(InventoryUnit::class);
    }
}

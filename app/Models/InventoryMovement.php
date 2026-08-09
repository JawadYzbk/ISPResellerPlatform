<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'inventory_unit_id', 'from_warehouse_id', 'to_warehouse_id', 'service_id', 'movement_type', 'actor_id', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Inventory movements are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Inventory movements are append-only.'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'inventory_unit_id');
    }
}

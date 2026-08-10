<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property Carbon $occurred_at */
class StockMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'inventory_item_id', 'warehouse_id', 'work_order_id', 'actor_id', 'movement_type', 'quantity', 'note', 'occurred_at', 'metadata'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'occurred_at' => 'datetime', 'metadata' => 'array'];
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

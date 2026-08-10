<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity
 * @property Carbon|null $consumed_at
 */
class WorkOrderMaterial extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'work_order_id', 'inventory_item_id', 'warehouse_id', 'consumed_by_id', 'quantity', 'note', 'consumed_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'consumed_at' => 'datetime'];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
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

    /** @return BelongsTo<User, $this> */
    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_id');
    }
}

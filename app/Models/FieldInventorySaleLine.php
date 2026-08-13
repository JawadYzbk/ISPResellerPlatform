<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $quantity
 * @property int $total_amount
 * @property InventoryItem|null $item
 */
class FieldInventorySaleLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'field_inventory_sale_id', 'inventory_item_id', 'quantity', 'unit_amount', 'total_amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_amount' => 'integer', 'total_amount' => 'integer'];
    }

    /** @return BelongsTo<FieldInventorySale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(FieldInventorySale::class, 'field_inventory_sale_id');
    }

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

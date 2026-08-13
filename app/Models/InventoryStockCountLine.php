<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $inventory_item_id
 * @property string $expected_quantity
 * @property string $counted_quantity
 * @property string $variance_quantity
 */
class InventoryStockCountLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'inventory_stock_count_id', 'inventory_item_id', 'expected_quantity', 'counted_quantity', 'variance_quantity'];

    protected function casts(): array
    {
        return ['expected_quantity' => 'decimal:3', 'counted_quantity' => 'decimal:3', 'variance_quantity' => 'decimal:3'];
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(InventoryStockCount::class, 'inventory_stock_count_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property Carbon|null $reviewed_at */
class InventoryTransferRequest extends Model
{
    use Auditable, BelongsToTenant;

    public const TYPES = ['replenishment', 'return'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'tenant_id', 'public_id', 'requested_by_id', 'reviewed_by_id', 'inventory_item_id',
        'source_warehouse_id', 'destination_warehouse_id', 'type', 'status', 'quantity',
        'note', 'review_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->public_id ??= (string) Str::ulid();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}

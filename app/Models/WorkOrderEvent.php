<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'work_order_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}

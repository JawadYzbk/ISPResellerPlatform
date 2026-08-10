<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property WorkOrderStatus $status */
class WorkOrder extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'type', 'customer_id', 'service_id', 'assigned_to', 'status', 'scheduled_at', 'started_at', 'completed_at', 'failure_reason', 'checklist', 'metadata'];

    protected function casts(): array
    {
        return ['status' => WorkOrderStatus::class, 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'checklist' => 'array', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class);
    }
}

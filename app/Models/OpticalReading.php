<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OpticalReading extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'optical_device_id', 'service_id', 'work_order_id', 'created_by_id', 'onu_serial', 'rx_dbm', 'tx_dbm', 'temperature_c', 'recorded_at', 'source', 'metadata'];

    protected function casts(): array
    {
        return [
            'rx_dbm' => 'decimal:2',
            'tx_dbm' => 'decimal:2',
            'temperature_c' => 'decimal:2',
            'recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reading): void {
            $reading->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function opticalDevice(): BelongsTo
    {
        return $this->belongsTo(OpticalDevice::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

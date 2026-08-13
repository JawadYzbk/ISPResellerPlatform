<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property WorkOrderStatus $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $checklist
 * @property array<string, mixed>|null $metadata
 * @property array<string, string>|null $readings
 * @property array<string, scalar|null>|null $installation_survey
 * @property Carbon|null $activation_accepted_at
 */
class WorkOrder extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'type', 'customer_id', 'service_id', 'network_building_id', 'distribution_box_id', 'network_port', 'assigned_to', 'status', 'scheduled_at', 'started_at', 'completed_at', 'activation_accepted_at', 'activation_accepted_by_id', 'activation_acceptance_note', 'onu_serial', 'installation_survey', 'completion_idempotency_key', 'failure_reason', 'checklist', 'readings', 'completion_notes', 'metadata'];

    protected function casts(): array
    {
        return ['status' => WorkOrderStatus::class, 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'activation_accepted_at' => 'datetime', 'network_building_id' => 'integer', 'distribution_box_id' => 'integer', 'network_port' => 'integer', 'activation_accepted_by_id' => 'integer', 'checklist' => 'array', 'readings' => 'array', 'installation_survey' => 'array', 'metadata' => 'array'];
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

    /** @return BelongsTo<Customer, $this> */
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Service, $this> */
    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<NetworkBuilding, $this> */
    public function networkBuilding(): BelongsTo
    {
        return $this->belongsTo(NetworkBuilding::class);
    }

    /** @return BelongsTo<DistributionBox, $this> */
    public function distributionBox(): BelongsTo
    {
        return $this->belongsTo(DistributionBox::class);
    }

    /** @return BelongsTo<User, $this> */
    public function activationAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activation_accepted_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<WorkOrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(WorkOrderEvent::class);
    }

    /** @return HasMany<MediaUpload, $this> */
    public function mediaUploads(): HasMany
    {
        return $this->hasMany(MediaUpload::class);
    }

    /** @return HasOne<WorkOrderSignature, $this> */
    public function signature(): HasOne
    {
        return $this->hasOne(WorkOrderSignature::class);
    }

    /** @return HasMany<WorkOrderMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }
}

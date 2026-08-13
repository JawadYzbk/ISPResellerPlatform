<?php

namespace App\Models;

use App\Enums\ServiceStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $capacity_ports
 * @property array<string, mixed>|null $metadata
 */
class DistributionBox extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'network_building_id', 'pop_id', 'name', 'code', 'box_type', 'capacity_ports', 'latitude', 'longitude', 'status', 'notes', 'metadata'];

    protected function casts(): array
    {
        return ['capacity_ports' => 'integer', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $box): void {
            $box->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<NetworkBuilding, $this> */
    public function building(): BelongsTo
    {
        return $this->belongsTo(NetworkBuilding::class, 'network_building_id');
    }

    /** @return BelongsTo<Pop, $this> */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function usedPorts(): int
    {
        return (int) $this->services()
            ->whereNotNull('network_port')
            ->where('status', '<>', ServiceStatus::Terminated->value)
            ->count();
    }
}

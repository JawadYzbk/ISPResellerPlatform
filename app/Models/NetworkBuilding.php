<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property array<string, mixed>|null $metadata
 */
class NetworkBuilding extends Model
{
    use BelongsToTenant;

    protected $table = 'network_buildings';

    protected $fillable = ['tenant_id', 'public_id', 'name', 'code', 'address', 'latitude', 'longitude', 'floors', 'unit_count', 'status', 'notes', 'metadata'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'floors' => 'integer', 'unit_count' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $building): void {
            $building->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<DistributionBox, $this> */
    public function distributionBoxes(): HasMany
    {
        return $this->hasMany(DistributionBox::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'network_building_id');
    }
}

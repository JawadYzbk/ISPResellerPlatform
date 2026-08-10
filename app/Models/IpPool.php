<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $version
 * @property bool $is_active
 * @property int $addresses_count
 * @property int $free_addresses_count
 */
class IpPool extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'router_id', 'name', 'cidr', 'gateway', 'type', 'version', 'is_active'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /** @return HasMany<IpAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }
}

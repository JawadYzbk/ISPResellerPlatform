<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $routers_count
 * @property int $upstream_links_count
 */
class Pop extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'code', 'address', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Router, $this> */
    public function routers(): HasMany
    {
        return $this->hasMany(Router::class);
    }

    /** @return HasMany<UpstreamLink, $this> */
    public function upstreamLinks(): HasMany
    {
        return $this->hasMany(UpstreamLink::class);
    }
}

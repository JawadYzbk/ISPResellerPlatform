<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property Carbon $route_date
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class CollectorRoute extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'public_id', 'user_id', 'planned_by_id', 'route_date',
        'status', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['route_date' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $route): void {
            $route->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function plannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'planned_by_id');
    }

    /** @return HasMany<CollectorRouteStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(CollectorRouteStop::class)->orderBy('position');
    }
}

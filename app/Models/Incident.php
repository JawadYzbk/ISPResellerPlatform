<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property IncidentStatus $status
 * @property string $public_id
 * @property Carbon $opened_at
 * @property Carbon|null $resolved_at
 */
class Incident extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'router_id', 'service_id', 'type', 'severity', 'status', 'title', 'description', 'opened_at', 'resolved_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => IncidentStatus::class, 'opened_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $incident): void {
            $incident->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

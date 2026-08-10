<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property Carbon $observed_at */
class DeviceMetric extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'router_id', 'metric', 'status', 'latency_ms', 'payload', 'observed_at'];

    protected function casts(): array
    {
        return ['latency_ms' => 'integer', 'payload' => 'array', 'observed_at' => 'datetime'];
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
}

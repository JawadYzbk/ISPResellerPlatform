<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property IncidentStatus $status */
class Incident extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'router_id', 'service_id', 'type', 'severity', 'status', 'title', 'description', 'opened_at', 'resolved_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => IncidentStatus::class, 'opened_at' => 'datetime', 'resolved_at' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

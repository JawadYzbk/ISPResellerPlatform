<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property Carbon|null $created_at */
class ServiceEvent extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'service_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

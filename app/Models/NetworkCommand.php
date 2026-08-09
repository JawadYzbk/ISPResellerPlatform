<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NetworkCommand extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'service_id', 'action', 'status', 'desired_state_version', 'attempts', 'available_at', 'started_at', 'completed_at', 'payload', 'result', 'last_error'];

    protected function casts(): array
    {
        return ['desired_state_version' => 'integer', 'attempts' => 'integer', 'available_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'payload' => 'array', 'result' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

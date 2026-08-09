<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutboxEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'event_type', 'aggregate_type', 'aggregate_id', 'payload', 'published_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'published_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

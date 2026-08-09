<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRun extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'run_type', 'period_key', 'status', 'processed_count', 'failed_count', 'started_at', 'completed_at', 'last_error', 'metadata'];

    protected function casts(): array
    {
        return ['processed_count' => 'integer', 'failed_count' => 'integer', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

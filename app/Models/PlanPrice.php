<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PlanPrice extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'plan_id', 'currency', 'amount_minor', 'effective_from', 'effective_to', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'effective_from' => 'datetime', 'effective_to' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isEffectiveAt(Carbon $at): bool
    {
        return $this->effective_from->lessThanOrEqualTo($at)
            && ($this->effective_to === null || $this->effective_to->greaterThan($at));
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class PlanUsageRate extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'public_id', 'plan_id', 'name', 'metric', 'included_bytes', 'unit_bytes',
        'amount_minor', 'currency', 'rounding', 'effective_from', 'effective_to', 'status', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'included_bytes' => 'integer',
            'unit_bytes' => 'integer',
            'amount_minor' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $rate): void {
            $rate->public_id ??= (string) Str::ulid();
            $rate->metadata ??= [];
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

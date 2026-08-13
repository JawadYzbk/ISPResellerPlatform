<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 */
class ServiceAddon extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'service_id', 'addon_id', 'quantity', 'starts_at', 'ends_at', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'starts_at' => 'date', 'ends_at' => 'date', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $serviceAddon): void {
            $serviceAddon->public_id ??= (string) Str::ulid();
            $serviceAddon->metadata ??= [];
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }
}

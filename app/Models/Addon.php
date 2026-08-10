<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Addon extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'name', 'slug', 'description', 'amount_minor', 'currency', 'billing_period_days', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'billing_period_days' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $addon): void {
            $addon->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

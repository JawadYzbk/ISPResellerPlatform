<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'slug', 'download_kbps', 'upload_kbps', 'duration_days', 'amount_minor', 'currency', 'status', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'amount_minor' => 'integer', 'download_kbps' => 'integer', 'upload_kbps' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $plan->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class OpticalDevice extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'pop_id', 'name', 'code', 'device_type', 'vendor', 'model', 'host', 'management_port', 'status', 'notes', 'metadata'];

    protected function casts(): array
    {
        return ['management_port' => 'integer', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $device): void {
            $device->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(OpticalReading::class);
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(OpticalReading::class)->latestOfMany('recorded_at');
    }
}

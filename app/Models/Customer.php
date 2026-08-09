<?php

namespace App\Models;

use App\Enums\CustomerStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['tenant_id', 'zone_id', 'code', 'first_name', 'last_name', 'phone', 'phone_normalized', 'email', 'address', 'latitude', 'longitude', 'status', 'balance_amount', 'balance_currency', 'notes'];

    protected $hidden = ['notes'];

    protected function casts(): array
    {
        return ['status' => CustomerStatus::class, 'balance_amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $customer): void {
            $customer->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (blank($search)) {
            return $query;
        }
        $term = trim($search);

        return $query->where(function ($query) use ($term): void {
            $query->where('code', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('phone_normalized', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}

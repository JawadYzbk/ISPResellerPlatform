<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ExpenseVendor extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'name', 'phone', 'email', 'tax_number', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $vendor): void {
            $vendor->public_id ??= (string) Str::ulid();
        });
    }

    /** @return HasMany<OperationalExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(OperationalExpense::class);
    }
}

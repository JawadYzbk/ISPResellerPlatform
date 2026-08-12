<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'code', 'contact_email', 'is_active', 'metadata'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function credentialBatches(): HasMany
    {
        return $this->hasMany(CredentialBatch::class);
    }

    /** @return HasMany<SupplierContract, $this> */
    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    /** @return HasMany<SupplierBill, $this> */
    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }
}

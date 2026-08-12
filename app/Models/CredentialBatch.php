<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CredentialBatch extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'supplier_id', 'supplier_contract_id', 'reference', 'contract_reference', 'unit_cost_amount', 'total_cost_amount', 'currency', 'imported_at', 'expires_at', 'metadata'];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'expires_at' => 'datetime', 'unit_cost_amount' => 'integer', 'total_cost_amount' => 'integer', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<SupplierContract, $this> */
    public function supplierContract(): BelongsTo
    {
        return $this->belongsTo(SupplierContract::class);
    }

    /** @return HasMany<UpstreamCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(UpstreamCredential::class);
    }
}

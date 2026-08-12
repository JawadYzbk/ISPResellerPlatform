<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 */
class SupplierContract extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'supplier_id', 'service_type', 'terms', 'wholesale_currency', 'effective_from', 'effective_to', 'status'];

    protected function casts(): array
    {
        return ['terms' => 'array', 'effective_from' => 'date', 'effective_to' => 'date'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<CredentialBatch, $this> */
    public function credentialBatches(): HasMany
    {
        return $this->hasMany(CredentialBatch::class);
    }
}

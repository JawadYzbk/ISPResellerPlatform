<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property Carbon $period_start @property Carbon $period_end */
class SupplierBill extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'supplier_id', 'reference', 'period_start', 'period_end', 'amount', 'currency', 'status', 'notes'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'amount' => 'integer'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }
}

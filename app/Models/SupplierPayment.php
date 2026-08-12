<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property Carbon $paid_at */
class SupplierPayment extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'supplier_bill_id', 'amount', 'currency', 'paid_at', 'method', 'reference', 'actor_id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    /** @return BelongsTo<SupplierBill, $this> */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'invoice_id', 'plan_id', 'service_id', 'description', 'quantity', 'unit_amount', 'total_amount', 'currency', 'price_snapshot'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_amount' => 'integer', 'total_amount' => 'integer', 'price_snapshot' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpstreamLink extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'pop_id', 'provider_name', 'capacity_mbps', 'monthly_cost_amount', 'currency', 'contract_start', 'contract_end', 'notes'];

    protected function casts(): array
    {
        return ['capacity_mbps' => 'integer', 'monthly_cost_amount' => 'integer', 'contract_start' => 'date', 'contract_end' => 'date'];
    }

    /** @return BelongsTo<Pop, $this> */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}

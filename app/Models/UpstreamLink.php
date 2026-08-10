<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $capacity_mbps
 * @property int $monthly_cost_amount
 * @property string $currency
 * @property Carbon $contract_start
 * @property Carbon|null $contract_end
 * @property string|null $notes
 */
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

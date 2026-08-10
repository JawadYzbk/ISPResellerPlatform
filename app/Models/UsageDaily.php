<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon $usage_date
 * @property int $input_octets
 * @property int $output_octets
 * @property int $total_octets
 */
class UsageDaily extends Model
{
    use BelongsToTenant;

    protected $table = 'usage_daily';

    protected $fillable = ['tenant_id', 'service_id', 'usage_date', 'input_octets', 'output_octets', 'total_octets', 'rolled_up_at'];

    protected function casts(): array
    {
        return ['usage_date' => 'date', 'input_octets' => 'integer', 'output_octets' => 'integer', 'total_octets' => 'integer', 'rolled_up_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

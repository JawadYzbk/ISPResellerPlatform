<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'base_currency', 'quote_currency', 'rate_numerator', 'rate_denominator', 'effective_from', 'source', 'metadata'];

    protected function casts(): array
    {
        return ['rate_numerator' => 'integer', 'rate_denominator' => 'integer', 'effective_from' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

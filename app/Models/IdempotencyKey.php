<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'request_hash', 'response_status', 'response_headers', 'response_body'];

    protected function casts(): array
    {
        return ['response_status' => 'integer', 'response_headers' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

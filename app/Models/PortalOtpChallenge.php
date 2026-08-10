<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalOtpChallenge extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'customer_id', 'phone_normalized', 'phone_hash', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'request_ip'];

    protected $hidden = ['phone_hash', 'code_hash'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

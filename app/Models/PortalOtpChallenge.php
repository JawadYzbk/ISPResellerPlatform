<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property string $public_id */
class PortalOtpChallenge extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'customer_id', 'phone_normalized', 'phone_hash', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'request_ip'];

    protected $hidden = ['phone_hash', 'code_hash'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'expires_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $challenge): void {
            $challenge->public_id ??= (string) Str::ulid();
        });
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

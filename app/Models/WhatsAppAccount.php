<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WhatsAppAccount extends Model
{
    use Auditable, BelongsToTenant;

    /** @var list<string> */
    public const JOBS = ['general', 'billing', 'collections', 'support', 'operations', 'marketing'];

    protected $fillable = [
        'tenant_id',
        'public_id',
        'label',
        'job',
        'bridge_id',
        'status',
        'phone',
        'push_name',
        'last_error',
        'last_ready_at',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_ready_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            $account->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}

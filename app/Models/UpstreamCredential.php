<?php

namespace App\Models;

use App\Enums\CredentialStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UpstreamCredential extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'credential_batch_id', 'identifier', 'secret', 'lookup_hash', 'status', 'assigned_service_id', 'reserved_at', 'assigned_at', 'expires_at', 'quota_limit', 'metadata'];

    protected $hidden = ['secret', 'lookup_hash'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'status' => CredentialStatus::class, 'reserved_at' => 'datetime', 'assigned_at' => 'datetime', 'expires_at' => 'datetime', 'quota_limit' => 'integer', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(CredentialBatch::class, 'credential_batch_id');
    }

    public function assignedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'assigned_service_id');
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(CredentialAssignment::class);
    }
}

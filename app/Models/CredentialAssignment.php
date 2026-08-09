<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAssignment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'upstream_credential_id', 'service_id', 'assigned_by', 'assigned_at', 'metadata'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(UpstreamCredential::class, 'upstream_credential_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'pop_id', 'name', 'host', 'api_port', 'username', 'password_encrypted', 'radius_secret_encrypted', 'coa_port', 'tls_verify', 'status', 'last_seen_at', 'metadata'];

    protected $hidden = ['password_encrypted', 'radius_secret_encrypted'];

    protected $attributes = ['coa_port' => 1700];

    protected function casts(): array
    {
        return ['api_port' => 'integer', 'password_encrypted' => 'encrypted', 'radius_secret_encrypted' => 'encrypted', 'coa_port' => 'integer', 'tls_verify' => 'boolean', 'last_seen_at' => 'datetime', 'metadata' => 'array'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /** @return HasMany<Service, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function baseUrl(): string
    {
        return 'https://'.$this->host.':'.$this->api_port;
    }
}

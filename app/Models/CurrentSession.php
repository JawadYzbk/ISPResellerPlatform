<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentSession extends Model
{
    use BelongsToTenant;

    protected $table = 'sessions_current';

    protected $fillable = ['tenant_id', 'service_id', 'username', 'acct_session_id', 'nasname', 'framed_ip', 'acct_start_time', 'last_seen_at', 'stopped_at', 'terminate_cause', 'input_octets', 'output_octets'];

    protected function casts(): array
    {
        return ['acct_start_time' => 'datetime', 'last_seen_at' => 'datetime', 'stopped_at' => 'datetime', 'input_octets' => 'integer', 'output_octets' => 'integer'];
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

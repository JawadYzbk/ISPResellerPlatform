<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $public_id
 * @property int $desired_state_version
 * @property int $attempts
 * @property Carbon|null $completed_at
 */
class NetworkCommand extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'service_id', 'action', 'status', 'desired_state_version', 'attempts', 'available_at', 'started_at', 'completed_at', 'payload', 'result', 'last_error'];

    protected function casts(): array
    {
        return ['desired_state_version' => 'integer', 'attempts' => 'integer', 'available_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'payload' => 'array', 'result' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $command): void {
            $command->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

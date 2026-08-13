<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 */
class CollectorTask extends Model
{
    use Auditable, BelongsToTenant;

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const STATUSES = ['assigned', 'acknowledged', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'tenant_id', 'public_id', 'collector_id', 'created_by_id', 'customer_id',
        'title', 'description', 'priority', 'status', 'due_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $task): void {
            $task->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CollectorTaskMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CollectorTaskMessage::class)->oldest();
    }

    /** @return HasMany<CollectorTaskRead, $this> */
    public function reads(): HasMany
    {
        return $this->hasMany(CollectorTaskRead::class);
    }
}

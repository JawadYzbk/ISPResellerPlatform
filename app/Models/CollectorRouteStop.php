<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property Carbon|null $visited_at */
class CollectorRouteStop extends Model
{
    use Auditable, BelongsToTenant;

    public const OUTCOMES = ['pending', 'collected', 'no_answer', 'refused', 'reschedule', 'address_issue'];

    protected $fillable = [
        'tenant_id', 'public_id', 'collector_route_id', 'customer_id', 'position',
        'outcome', 'note', 'visited_at', 'latitude', 'longitude', 'accuracy_meters',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'visited_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $stop): void {
            $stop->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<CollectorRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(CollectorRoute::class, 'collector_route_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

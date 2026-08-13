<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon $checked_in_at
 * @property Carbon|null $checked_out_at
 */
class CollectorFieldDay extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'public_id', 'user_id', 'checked_in_at',
        'check_in_latitude', 'check_in_longitude', 'check_in_accuracy_meters',
        'checked_out_at', 'check_out_latitude', 'check_out_longitude',
        'check_out_accuracy_meters', 'check_in_source', 'check_out_source',
        'summary', 'summary_note',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'check_in_latitude' => 'decimal:7',
            'check_in_longitude' => 'decimal:7',
            'check_out_latitude' => 'decimal:7',
            'check_out_longitude' => 'decimal:7',
            'check_in_accuracy_meters' => 'integer',
            'check_out_accuracy_meters' => 'integer',
            'summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $fieldDay): void {
            $fieldDay->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

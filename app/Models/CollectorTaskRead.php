<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property Carbon $last_read_at */
class CollectorTaskRead extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'collector_task_id', 'user_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }

    /** @return BelongsTo<CollectorTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(CollectorTask::class, 'collector_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

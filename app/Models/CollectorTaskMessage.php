<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CollectorTaskMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'collector_task_id', 'author_id', 'body'];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<CollectorTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(CollectorTask::class, 'collector_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return HasMany<MediaUpload, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(MediaUpload::class);
    }
}

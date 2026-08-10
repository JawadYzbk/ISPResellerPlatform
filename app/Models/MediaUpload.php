<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MediaUpload extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'uploaded_by_id', 'work_order_id', 'public_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'purpose'];

    protected $hidden = ['path', 'sha256'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Carbon $signed_at
 */
class WorkOrderSignature extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'work_order_id', 'media_upload_id', 'captured_by_id', 'signer_name', 'signed_at'];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<MediaUpload, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaUpload::class, 'media_upload_id');
    }

    /** @return BelongsTo<User, $this> */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_id');
    }
}

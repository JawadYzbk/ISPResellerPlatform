<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Carbon|null $retention_until
 */
class MediaUpload extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'uploaded_by_id', 'customer_id', 'work_order_id', 'collector_task_message_id', 'operational_expense_id', 'public_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'purpose', 'document_type', 'retention_until'];

    protected $hidden = ['path', 'sha256'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'retention_until' => 'date'];
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

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<CollectorTaskMessage, $this> */
    public function collectorTaskMessage(): BelongsTo
    {
        return $this->belongsTo(CollectorTaskMessage::class);
    }

    /** @return BelongsTo<OperationalExpense, $this> */
    public function operationalExpense(): BelongsTo
    {
        return $this->belongsTo(OperationalExpense::class);
    }
}

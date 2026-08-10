<?php

namespace App\Models;

use App\Enums\MessageStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property Customer|null $customer
 */
class Message extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'customer_id', 'channel', 'recipient', 'template_key', 'locale', 'subject', 'body', 'status', 'delivery_attempts', 'provider', 'provider_message_id', 'idempotency_key', 'sent_at', 'delivered_at', 'failed_at', 'failure_reason', 'metadata'];

    protected function casts(): array
    {
        return ['status' => MessageStatus::class, 'delivery_attempts' => 'integer', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $message->public_id ??= (string) Str::ulid();
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

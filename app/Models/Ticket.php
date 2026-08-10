<?php

namespace App\Models;

use App\Domain\Support\TicketSlaClock;
use App\Enums\TicketStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property TicketStatus $status
 * @property CarbonInterface|null $due_at
 * @property CarbonInterface|null $resolved_at
 */
class Ticket extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['tenant_id', 'public_id', 'number', 'customer_id', 'service_id', 'subject', 'description', 'priority', 'status', 'assigned_to', 'due_at', 'resolved_at', 'closed_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => TicketStatus::class, 'due_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'metadata' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket): void {
            $ticket->public_id ??= (string) Str::ulid();
            if ($ticket->due_at === null && app(Tenancy::class)->id() !== null) {
                $tenant = Tenant::find(app(Tenancy::class)->requireId());
                if ($tenant !== null) {
                    $ticket->due_at = app(TicketSlaClock::class)->dueAt($tenant, (string) ($ticket->priority ?: 'normal'), now()->toImmutable());
                }
            }
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class);
    }

    /** @return HasMany<TicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }
}

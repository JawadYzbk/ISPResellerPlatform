<?php

namespace App\Models;

use App\Enums\NetworkState;
use App\Enums\ProvisioningMode;
use App\Enums\ServiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property ServiceStatus $status
 * @property NetworkState $network_state
 * @property ProvisioningMode $provisioning_mode
 * @property string $public_id
 * @property int $current_period_bytes
 * @property array<string, mixed>|null $metadata
 * @property string|null $suspension_reason
 * @property Carbon|null $activated_at
 * @property Carbon|null $fup_applied_at
 * @property Carbon|null $expires_at
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = ['tenant_id', 'customer_id', 'plan_id', 'router_id', 'username', 'password_encrypted', 'status', 'provisioning_mode', 'network_state', 'desired_state_version', 'current_period_bytes', 'fup_applied_at', 'activated_at', 'expires_at', 'suspension_reason', 'metadata'];

    protected $hidden = ['password_encrypted'];

    protected function casts(): array
    {
        return ['status' => ServiceStatus::class, 'provisioning_mode' => ProvisioningMode::class, 'network_state' => NetworkState::class, 'password_encrypted' => 'encrypted', 'metadata' => 'array', 'activated_at' => 'datetime', 'expires_at' => 'datetime', 'fup_applied_at' => 'datetime', 'desired_state_version' => 'integer', 'current_period_bytes' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $service): void {
            $service->public_id ??= (string) Str::ulid();
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Router, $this> */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /** @return HasMany<ServiceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ServiceEvent::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** @param Builder<Service> $query */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (blank($search)) {
            return $query;
        }
        $term = trim($search);

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('username', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $customer): Builder => $customer->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('phone_normalized', 'like', "%{$term}%"));
        });
    }
}

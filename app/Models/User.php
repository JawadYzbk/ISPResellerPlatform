<?php

namespace App\Models;

use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['tenant_id', 'partner_id', 'name', 'email', 'password', 'role', 'locale', 'timezone', 'default_view', 'collector_all_zones'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
/**
 * @property Carbon|null $last_authenticated_at
 * @property int|null $partner_id
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    /** @var array<string, mixed> */
    protected $attributes = [
        'collector_all_zones' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_authenticated_at' => 'datetime',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'collector_all_zones' => 'boolean',
        ];
    }

    public function requiresTwoFactor(): bool
    {
        return in_array($this->role, ['admin', 'platform_operator', 'tenant_owner', 'operations_manager', 'billing_manager', 'network_administrator', 'reseller_owner'], true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Partner, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /** @return HasMany<CollectorZoneAssignment, $this> */
    public function collectorZoneAssignments(): HasMany
    {
        return $this->hasMany(CollectorZoneAssignment::class);
    }

    /** @return HasMany<CollectorZoneAssignment, $this> */
    public function activeCollectorZoneAssignments(): HasMany
    {
        return $this->collectorZoneAssignments()->whereNull('ended_at');
    }

    /** @return HasMany<CollectorFieldDay, $this> */
    public function collectorFieldDays(): HasMany
    {
        return $this->hasMany(CollectorFieldDay::class);
    }

    /** @return HasMany<CollectorRoute, $this> */
    public function collectorRoutes(): HasMany
    {
        return $this->hasMany(CollectorRoute::class);
    }

    /** @return HasMany<CollectorTask, $this> */
    public function collectorTasks(): HasMany
    {
        return $this->hasMany(CollectorTask::class, 'collector_id');
    }

    /** @return HasMany<CollectorCustodyEntry, $this> */
    public function collectorCustodyEntries(): HasMany
    {
        return $this->hasMany(CollectorCustodyEntry::class, 'collector_id');
    }

    public function isPlatformOperator(): bool
    {
        return $this->tenant_id === null && $this->role === 'platform_operator';
    }
}

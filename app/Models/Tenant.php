<?php

namespace App\Models;

use App\Data\TenantSettings;
use App\Support\TenantProvisioner;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property array<string, mixed>|null $settings */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = ['name', 'logo_path', 'slug', 'status', 'base_currency', 'collection_currency', 'timezone', 'locale', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tenant): void {
            $tenant->public_id ??= (string) Str::ulid();
            $tenant->settings ??= (new TenantSettings(
                locale: $tenant->locale ?: 'en',
                timezone: $tenant->timezone ?: 'UTC',
                baseCurrency: $tenant->base_currency ?: 'USD',
                collectionCurrency: $tenant->collection_currency ?: 'USD',
                rtl: in_array($tenant->locale ?: 'en', ['ar', 'fa', 'he', 'ur'], true),
            ))->toArray();
        });
        static::created(function (self $tenant): void {
            app(TenantProvisioner::class)->provision($tenant);
        });
        static::updated(function (self $tenant): void {
            if ($tenant->wasChanged(['base_currency', 'collection_currency', 'locale'])) {
                app(TenantProvisioner::class)->provision($tenant);
            }
        });
    }

    public function settingsData(): TenantSettings
    {
        return TenantSettings::fromTenant($this);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}

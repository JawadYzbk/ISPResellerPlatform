<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

final class TenantIntegrationSettings
{
    /** @var list<string> */
    private const CONFIG_PATHS = [
        'payments.driver',
        'frankfurter.enabled',
        'frankfurter.currency_catalog_enabled',
        'frankfurter.endpoint',
        'frankfurter.connect_timeout',
        'frankfurter.timeout',
        'frankfurter.quotes',
        'whatsapp.mode',
        'whatsapp.token',
        'whatsapp.phone_number_id',
        'whatsapp.web.enabled',
        'whatsapp.web.endpoint',
        'whatsapp.web.token',
        'whatsapp.web.client_id',
        'whatsapp.web.webhook_url',
        'webhooks.secrets.whatsapp',
        'webhooks.secrets.whatsapp_web',
        'stripe.secret',
        'stripe.publishable_key',
        'stripe.endpoint',
        'stripe.webhook_secret',
        'stripe.webhook_tolerance',
        'stripe.timeout',
        'whish.enabled',
        'whish.environment',
        'whish.channel',
        'whish.secret',
        'whish.website_url',
        'whish.endpoint',
        'whish.timeout',
        'whish.success_callback_url',
        'whish.failure_callback_url',
        'whish.success_redirect_url',
        'whish.failure_redirect_url',
    ];

    /** @var array<string, mixed> */
    private array $defaults;

    public function __construct()
    {
        $this->defaults = collect(self::CONFIG_PATHS)
            ->mapWithKeys(fn (string $path): array => [$path => config('services.'.$path)])
            ->all();
    }

    public function apply(Tenant $tenant): void
    {
        $this->reset();

        foreach ($this->resolved($tenant) as $path => $value) {
            config(['services.'.$path => $value]);
        }
    }

    public function reset(): void
    {
        foreach ($this->defaults as $path => $value) {
            config(['services.'.$path => $value]);
        }
    }

    /** @return array<string, mixed> */
    public function resolved(Tenant $tenant): array
    {
        $stored = is_array($tenant->provider_settings) ? $tenant->provider_settings : [];

        return array_replace($this->defaults, array_intersect_key($stored, $this->defaults));
    }

    /** @return array<string, mixed> */
    public function stored(Tenant $tenant): array
    {
        return is_array($tenant->provider_settings) ? $tenant->provider_settings : [];
    }

    /** @param array<string, mixed> $settings */
    public function save(Tenant $tenant, array $settings): Tenant
    {
        return DB::transaction(function () use ($tenant, $settings): Tenant {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);
            $locked->forceFill([
                'provider_settings' => array_intersect_key($settings, $this->defaults),
            ])->save();

            $this->apply($locked);

            return $locked->refresh();
        });
    }
}

<?php

namespace App\Actions;

use App\Contracts\Action;
use App\Models\Tenant;
use App\Support\TenantIntegrationSettings;

final readonly class UpdateTenantIntegrationSettings implements Action
{
    public function __construct(private TenantIntegrationSettings $settings) {}

    /** @param array<string, mixed> $data */
    public function handle(Tenant $tenant, array $data): Tenant
    {
        $stored = $this->settings->stored($tenant);

        $fields = [
            'payment_driver' => 'payments.driver',
            'frankfurter_enabled' => 'frankfurter.enabled',
            'frankfurter_currency_catalog_enabled' => 'frankfurter.currency_catalog_enabled',
            'frankfurter_endpoint' => 'frankfurter.endpoint',
            'frankfurter_connect_timeout' => 'frankfurter.connect_timeout',
            'frankfurter_timeout' => 'frankfurter.timeout',
            'whatsapp_mode' => 'whatsapp.mode',
            'whatsapp_web_enabled' => 'whatsapp.web.enabled',
            'whatsapp_web_endpoint' => 'whatsapp.web.endpoint',
            'whatsapp_web_client_id' => 'whatsapp.web.client_id',
            'whatsapp_web_webhook_url' => 'whatsapp.web.webhook_url',
            'stripe_endpoint' => 'stripe.endpoint',
            'stripe_webhook_tolerance' => 'stripe.webhook_tolerance',
            'stripe_timeout' => 'stripe.timeout',
            'whish_enabled' => 'whish.enabled',
            'whish_environment' => 'whish.environment',
            'whish_website_url' => 'whish.website_url',
            'whish_endpoint' => 'whish.endpoint',
            'whish_timeout' => 'whish.timeout',
            'whish_success_callback_url' => 'whish.success_callback_url',
            'whish_failure_callback_url' => 'whish.failure_callback_url',
            'whish_success_redirect_url' => 'whish.success_redirect_url',
            'whish_failure_redirect_url' => 'whish.failure_redirect_url',
        ];

        foreach ($fields as $field => $path) {
            if (array_key_exists($field, $data)) {
                $stored[$path] = $data[$field];
            }
        }

        $stored['frankfurter.quotes'] = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($data['frankfurter_quotes'] ?? '')),
        )));

        $secrets = [
            'whatsapp_cloud_token' => 'whatsapp.token',
            'whatsapp_phone_number_id' => 'whatsapp.phone_number_id',
            'whatsapp_web_token' => 'whatsapp.web.token',
            'whatsapp_webhook_secret' => 'webhooks.secrets.whatsapp_web',
            'whatsapp_cloud_webhook_secret' => 'webhooks.secrets.whatsapp',
            'stripe_secret' => 'stripe.secret',
            'stripe_publishable_key' => 'stripe.publishable_key',
            'stripe_webhook_secret' => 'stripe.webhook_secret',
            'whish_channel' => 'whish.channel',
            'whish_secret' => 'whish.secret',
        ];

        foreach ($secrets as $field => $path) {
            if (($data['clear_'.$field] ?? false) === true) {
                unset($stored[$path]);
            } elseif (is_string($data[$field] ?? null) && trim($data[$field]) !== '') {
                $stored[$path] = trim($data[$field]);
            }
        }

        return $this->settings->save($tenant, $stored);
    }
}

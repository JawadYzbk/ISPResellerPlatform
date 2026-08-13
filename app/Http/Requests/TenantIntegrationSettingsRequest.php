<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TenantIntegrationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'payment_driver' => ['required', Rule::in(['null', 'stripe'])],
            'frankfurter_enabled' => ['boolean'],
            'frankfurter_currency_catalog_enabled' => ['boolean'],
            'frankfurter_endpoint' => ['nullable', 'url', 'max:255'],
            'frankfurter_connect_timeout' => ['required', 'integer', 'between:1,30'],
            'frankfurter_timeout' => ['required', 'integer', 'between:1,120'],
            'frankfurter_quotes' => ['required', 'string', 'regex:/^[A-Z]{3}(\s*,\s*[A-Z]{3})*$/'],
            'whatsapp_mode' => ['required', Rule::in(['cloud', 'web'])],
            'whatsapp_web_enabled' => ['boolean'],
            'whatsapp_web_endpoint' => ['nullable', 'url', 'max:255'],
            'whatsapp_web_client_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_web_webhook_url' => ['nullable', 'url', 'max:255'],
            'whatsapp_cloud_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_phone_number_id' => ['nullable', 'string', 'max:120'],
            'whatsapp_web_token' => ['nullable', 'string', 'max:1000'],
            'whatsapp_webhook_secret' => ['nullable', 'string', 'max:1000'],
            'whatsapp_cloud_webhook_secret' => ['nullable', 'string', 'max:1000'],
            'stripe_endpoint' => ['nullable', 'url', 'max:255'],
            'stripe_webhook_tolerance' => ['required', 'integer', 'between:1,900'],
            'stripe_timeout' => ['required', 'integer', 'between:1,120'],
            'stripe_secret' => ['nullable', 'string', 'max:1000'],
            'stripe_publishable_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:1000'],
            'whish_enabled' => ['boolean'],
            'whish_environment' => ['required', Rule::in(['sandbox', 'production'])],
            'whish_website_url' => ['nullable', 'url', 'max:255'],
            'whish_endpoint' => ['nullable', 'url', 'max:255'],
            'whish_timeout' => ['required', 'integer', 'between:1,120'],
            'whish_channel' => ['nullable', 'string', 'max:255'],
            'whish_secret' => ['nullable', 'string', 'max:1000'],
            'whish_success_callback_url' => ['nullable', 'url', 'max:255'],
            'whish_failure_callback_url' => ['nullable', 'url', 'max:255'],
            'whish_success_redirect_url' => ['nullable', 'url', 'max:255'],
            'whish_failure_redirect_url' => ['nullable', 'url', 'max:255'],
            'clear_whatsapp_cloud_token' => ['boolean'],
            'clear_whatsapp_phone_number_id' => ['boolean'],
            'clear_whatsapp_web_token' => ['boolean'],
            'clear_whatsapp_webhook_secret' => ['boolean'],
            'clear_whatsapp_cloud_webhook_secret' => ['boolean'],
            'clear_stripe_secret' => ['boolean'],
            'clear_stripe_publishable_key' => ['boolean'],
            'clear_stripe_webhook_secret' => ['boolean'],
            'clear_whish_channel' => ['boolean'],
            'clear_whish_secret' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_driver' => $this->string('payment_driver')->trim()->toString(),
            'frankfurter_enabled' => $this->boolean('frankfurter_enabled'),
            'frankfurter_currency_catalog_enabled' => $this->boolean('frankfurter_currency_catalog_enabled'),
            'frankfurter_quotes' => strtoupper((string) preg_replace('/\s+/', '', $this->string('frankfurter_quotes')->toString())),
            'whatsapp_mode' => $this->string('whatsapp_mode')->trim()->toString(),
            'whatsapp_web_enabled' => $this->boolean('whatsapp_web_enabled'),
            'whish_enabled' => $this->boolean('whish_enabled'),
        ]);
    }
}

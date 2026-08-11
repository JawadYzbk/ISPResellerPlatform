<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TenantSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'locale' => ['required', 'in:en,ar,fr'],
            'timezone' => ['required', 'timezone'],
            'base_currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'collection_currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'date_format' => ['required', 'string', 'max:32'],
            'time_format' => ['required', 'string', 'max:32'],
            'rtl' => ['boolean'],
            'grace_extends_period' => ['boolean'],
            'notification_quiet_start' => ['required', 'date_format:H:i'],
            'notification_quiet_end' => ['required', 'date_format:H:i'],
            'resolved_ticket_auto_close_hours' => ['required', 'integer', 'between:1,720'],
            'radius_interim_interval_seconds' => ['required', 'integer', 'between:30,3600'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'base_currency' => strtoupper($this->string('base_currency')->toString()),
            'collection_currency' => strtoupper($this->string('collection_currency')->toString()),
            'rtl' => $this->boolean('rtl'),
            'grace_extends_period' => $this->boolean('grace_extends_period'),
        ]);
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\ProvisioningMode;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tenantId = app(Tenancy::class)->requireId();

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'zone_id' => ['nullable', Rule::exists('zones', 'id')->where(fn ($query) => $query->where('tenant_id', app(Tenancy::class)->requireId()))],
            'address' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'create_service' => ['sometimes', 'boolean'],
            'plan_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->boolean('create_service')),
                Rule::exists('plans', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active')),
            ],
            'username' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9._@:-]+$/',
                Rule::requiredIf(fn (): bool => $this->boolean('create_service')),
                Rule::unique('services', 'username')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'password' => ['nullable', 'string', 'min:12', 'max:128', Rule::requiredIf(fn (): bool => $this->boolean('create_service'))],
            'provisioning_mode' => ['nullable', Rule::enum(ProvisioningMode::class), Rule::requiredIf(fn (): bool => $this->boolean('create_service'))],
            'billing_anchor_day' => ['nullable', 'integer', 'between:1,31'],
            'router_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->boolean('create_service') && in_array($this->string('provisioning_mode')->toString(), [ProvisioningMode::Mikrotik->value, ProvisioningMode::Radius->value], true)),
                Rule::exists('routers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}

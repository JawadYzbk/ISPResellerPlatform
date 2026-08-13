<?php

namespace App\Http\Requests;

use App\Enums\ProvisioningMode;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateServiceApiRequest extends FormRequest
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
            'customer_id' => ['required', 'string', Rule::exists('customers', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'plan_id' => ['required', 'string', Rule::exists('plans', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._@:-]+$/', Rule::unique('services', 'username')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'password' => ['required', 'string', 'min:12', 'max:128'],
            'provisioning_mode' => ['required', Rule::enum(ProvisioningMode::class)],
            'billing_anchor_day' => ['nullable', 'integer', 'between:1,31'],
            'router_id' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => in_array($this->string('provisioning_mode')->toString(), [ProvisioningMode::Mikrotik->value, ProvisioningMode::Radius->value], true)),
                Rule::exists('routers', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}

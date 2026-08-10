<?php

namespace App\Http\Requests;

use App\Enums\ProvisioningMode;
use App\Support\Tenancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateServiceRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', 'active'))],
            'username' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._@:-]+$/', Rule::unique('services', 'username')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'password' => ['required', 'string', 'min:12', 'max:128'],
            'provisioning_mode' => ['required', Rule::enum(ProvisioningMode::class)],
            'router_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => in_array($this->string('provisioning_mode')->toString(), [ProvisioningMode::Mikrotik->value, ProvisioningMode::Radius->value], true)),
                Rule::exists('routers', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ];
    }
}
